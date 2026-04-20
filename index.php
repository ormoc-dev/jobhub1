<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WORKLINK - Connecting Talent with Opportunity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        .priority-section {
            background: linear-gradient(135deg, #ecfdf5 0%, #f0f9ff 100%);
        }
        .priority-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .priority-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        }
        .priority-badge {
            background: #f59e0b;
            color: #111827;
            font-weight: 600;
        }
        .priority-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #10b981;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
    </style>
</head>
<body class="wl-theme">
    <?php include 'config.php'; ?>
    <?php
    $priorityJobSeekers = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.status,
                ep.first_name,
                ep.last_name,
                ep.address,
                CASE WHEN paid.user_id IS NULL THEN 0 ELSE 1 END AS is_priority
            FROM users u
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            LEFT JOIN (
                SELECT us.user_id
                FROM user_subscriptions us
                JOIN subscription_plans sp ON us.plan_id = sp.id
                WHERE us.status = 'active'
                AND us.payment_status = 'paid'
                AND (us.end_date IS NULL OR us.end_date > NOW())
                AND sp.role IN ('employee', 'both')
                GROUP BY us.user_id
            ) paid ON paid.user_id = u.id
            WHERE u.role = 'employee' AND u.status = 'active'
            ORDER BY is_priority DESC, u.id DESC
            LIMIT 6
        ");
        $stmt->execute();
        $priorityJobSeekers = $stmt->fetchAll();
    } catch (Exception $e) {
        $priorityJobSeekers = [];
    }
    ?>
    
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="companies.php">Companies</a>
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

    <!-- Hero Section with Image Slider -->
    <section class="hero-section position-relative">
        <!-- Image Slider -->
        <div id="heroSlider" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="2"></button>
                <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="3"></button>
                <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="4"></button>
            </div>
            
            <!-- Slide Counter -->
            <div class="slide-counter" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.7); color: white; padding: 10px 15px; border-radius: 20px; font-size: 14px; z-index: 10;">
                <span id="currentSlide">1</span> / <span id="totalSlides">5</span>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <div class="hero-slide" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/1.jpg') center/cover;">
                        <div class="container-fluid h-100">
                            <div class="row h-100 align-items-center justify-content-center">
                                <div class="col-lg-10 col-xl-8 hero-content text-center">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Find Your Dream Job with <span class="text-accent">WORKLINK</span>
                    </h1>
                    <p class="lead text-white mb-4">
                        Connect with top employers and discover opportunities that match your skills and career goals.
                    </p>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a href="jobs.php" class="btn btn-lg px-4 btn-accent">
                            <i class="fas fa-search me-2"></i>Find Jobs
                        </a>
                        <a href="register.php" class="btn btn-lg px-4 btn-accent">
                            <i class="fas fa-user-plus me-2"></i>Join Now
                        </a>
                    </div>
                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="hero-slide" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/2.jpg') center/cover;">
                        <div class="container-fluid h-100">
                            <div class="row h-100 align-items-center justify-content-center">
                                <div class="col-lg-10 col-xl-8 hero-content text-center">
                                    <h1 class="display-4 fw-bold text-white mb-4">
                                        Professional Growth <span class="text-accent">Opportunities</span>
                                    </h1>
                                    <p class="lead text-white mb-4">
                                        Advance your career with opportunities from leading companies across various industries.
                                    </p>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a href="jobs.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-search me-2"></i>Explore Jobs
                                        </a>
                                        <a href="register.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-user-plus me-2"></i>Get Started
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="hero-slide" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('1.jpg') center/cover;">
                        <div class="container-fluid h-100">
                            <div class="row h-100 align-items-center justify-content-center">
                                <div class="col-lg-10 col-xl-8 hero-content text-center">
                                    <h1 class="display-4 fw-bold text-white mb-4">
                                        Hire the Best <span class="text-accent">Talent</span>
                                    </h1>
                                    <p class="lead text-white mb-4">
                                        For employers: Find qualified candidates and build your dream team with our platform.
                                    </p>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a href="register.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-building me-2"></i>Post Jobs
                                        </a>
                                        <a href="companies.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-eye me-2"></i>View Companies
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="hero-slide" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/4.jpg') center/cover;">
                        <div class="container-fluid h-100">
                            <div class="row h-100 align-items-center justify-content-center">
                                <div class="col-lg-10 col-xl-8 hero-content text-center">
                                    <h1 class="display-4 fw-bold text-white mb-4">
                                        Remote & <span class="text-accent">Flexible</span> Work
                                    </h1>
                                    <p class="lead text-white mb-4">
                                        Discover remote work opportunities and flexible schedules that fit your lifestyle.
                                    </p>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a href="jobs.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-home me-2"></i>Remote Jobs
                                        </a>
                                        <a href="register.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-user-plus me-2"></i>Join Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="hero-slide" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/5.jpg') center/cover;">
                        <div class="container-fluid h-100">
                            <div class="row h-100 align-items-center justify-content-center">
                                <div class="col-lg-10 col-xl-8 hero-content text-center">
                                    <h1 class="display-4 fw-bold text-white mb-4">
                                        Success <span class="text-accent">Stories</span>
                                    </h1>
                                    <p class="lead text-white mb-4">
                                        Join thousands of professionals who found their perfect career match through WORKLINK.
                                    </p>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a href="about.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-info-circle me-2"></i>Learn More
                                        </a>
                                        <a href="register.php" class="btn btn-lg px-4 btn-accent">
                                            <i class="fas fa-star me-2"></i>Start Your Journey
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </section>

    <!-- Priority Job Seekers Section -->
    <section class="py-5 priority-section">
        <div class="container">
            <div class="row text-center mb-4">
                <div class="col">
                    <h2 class="fw-bold section-title">Priority Job Seekers</h2>
                    <p class="text-muted section-subtitle">Paid subscribers appear first for employer visibility.</p>
                </div>
            </div>
            <div class="row g-4">
                <?php if (empty($priorityJobSeekers)): ?>
                    <div class="col-12">
                        <div class="text-center text-muted">
                            No job seekers available right now.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($priorityJobSeekers as $seeker): ?>
                        <?php
                        $fullName = trim(($seeker['first_name'] ?? '') . ' ' . ($seeker['last_name'] ?? ''));
                        $displayName = $fullName ?: ($seeker['username'] ?? 'Job Seeker');
                        $initials = strtoupper(substr($displayName, 0, 1));
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card priority-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="priority-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                        <?php if ((int)$seeker['is_priority'] === 1): ?>
                                            <span class="badge priority-badge"><i class="fas fa-crown me-1"></i>Priority</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Standard</span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($displayName); ?></h5>
                                    <div class="text-muted small mb-2">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($seeker['email']); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($seeker['address'] ?? 'Location not set'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 home-why-section">
        <div class="container">
            <div class="row justify-content-center mb-5 pb-lg-2">
                <div class="col-lg-8 text-center">
                    <p class="home-eyebrow mb-2">Why WORKLINK</p>
                    <h2 class="home-section-heading mb-3">Your gateway to career success</h2>
                    <p class="home-lead text-muted mb-0">One platform for discovery, applications, and hiring—clear, fast, and built for real workplaces.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <article class="home-feature-card h-100">
                        <div class="home-feature-icon home-feature-icon--seeker" aria-hidden="true">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="home-feature-title">For job seekers</h3>
                        <p class="home-feature-text">Browse roles, apply in a few steps, and keep every application organized in one place.</p>
                        <ul class="home-feature-bullets">
                            <li><i class="fas fa-check"></i> Smart search &amp; filters</li>
                            <li><i class="fas fa-check"></i> Application tracking</li>
                        </ul>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="home-feature-card h-100">
                        <div class="home-feature-icon home-feature-icon--employer" aria-hidden="true">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="home-feature-title">For employers</h3>
                        <p class="home-feature-text">Publish openings, review candidates, and shorten the path from post to hire.</p>
                        <ul class="home-feature-bullets">
                            <li><i class="fas fa-check"></i> Job post management</li>
                            <li><i class="fas fa-check"></i> Structured applications</li>
                        </ul>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="home-feature-card h-100">
                        <div class="home-feature-icon home-feature-icon--match" aria-hidden="true">
                            <i class="fas fa-link"></i>
                        </div>
                        <h3 class="home-feature-title">The right match</h3>
                        <p class="home-feature-text">We focus on clarity and fit so people and companies find each other faster.</p>
                        <ul class="home-feature-bullets">
                            <li><i class="fas fa-check"></i> Trusted local network</li>
                            <li><i class="fas fa-check"></i> Support for both sides</li>
                        </ul>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5 home-stats-section">
        <div class="container">
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'");
            $activeJobs = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
            $activeCompanies = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM employee_profiles");
            $totalEmployees = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM job_applications");
            $totalApplications = $stmt->fetchColumn();
            ?>
            <div class="home-stats-panel">
                <div class="row g-0">
                    <div class="col-6 col-lg-3">
                        <div class="home-stat-cell">
                            <span class="home-stat-icon home-stat-icon--jobs"><i class="fas fa-briefcase"></i></span>
                            <span class="home-stat-value"><?php echo (int) $activeJobs; ?></span>
                            <span class="home-stat-label">Active jobs</span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="home-stat-cell">
                            <span class="home-stat-icon home-stat-icon--companies"><i class="fas fa-city"></i></span>
                            <span class="home-stat-value"><?php echo (int) $activeCompanies; ?></span>
                            <span class="home-stat-label">Companies</span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="home-stat-cell">
                            <span class="home-stat-icon home-stat-icon--seekers"><i class="fas fa-users"></i></span>
                            <span class="home-stat-value"><?php echo (int) $totalEmployees; ?></span>
                            <span class="home-stat-label">Job seekers</span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="home-stat-cell">
                            <span class="home-stat-icon home-stat-icon--apps"><i class="fas fa-paper-plane"></i></span>
                            <span class="home-stat-value"><?php echo (int) $totalApplications; ?></span>
                            <span class="home-stat-label">Applications</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="home-footer">
        <div class="container">
            <div class="row align-items-start align-items-lg-center gy-4 py-4 py-lg-5">
                <div class="col-lg-5">
                    <a href="index.php" class="d-inline-block mb-3">
                        <img src="images/LOGO.png" alt="WORKLINK Job Seeker System" class="home-footer-logo">
                    </a>
                    <p class="home-footer-tagline mb-0">Connecting talent with opportunity.</p>
                </div>
                <div class="col-lg-4">
                    <p class="home-footer-nav-title mb-2">Explore</p>
                    <nav class="home-footer-nav" aria-label="Footer">
                        <a href="jobs.php">Jobs</a>
                        <a href="companies.php">Companies</a>
                        <a href="about.php">About</a>
                        <a href="contact.php">Contact</a>
                    </nav>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <p class="home-footer-copy mb-1">&copy; <?php echo date('Y'); ?> WORKLINK. All rights reserved.</p>
                    <p class="home-footer-note mb-0">Job Seeker System</p>
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

        .btn-ghost {
            border-color: rgba(255, 255, 255, 0.7);
            color: #ffffff;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #ffffff;
        }
        .hero-section {
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            margin-top: 0;
            background: #0b1220;
            padding: 0;
        }
        
        .hero-slide {
            min-height: 100vh;
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 100%;
        }

        .hero-slide::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(15, 23, 42, 0.75), rgba(30, 58, 138, 0.22));
            z-index: 1;
        }

        .hero-content {
            z-index: 5;
            position: relative;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            background: rgba(15, 23, 42, 0.45);
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(6px);
        }
        
        .carousel {
            height: 100vh;
            width: 100%;
        }
        
        .carousel-inner {
            height: 100vh;
            width: 100%;
        }
        
        .carousel-item {
            height: 100vh;
            width: 100%;
            transition: transform 0.6s ease-in-out;
        }
        
        .carousel-indicators {
            bottom: 30px;
            z-index: 10;
        }
        
        .carousel-indicators button {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin: 0 8px;
            background-color: rgba(255, 255, 255, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
        }
        
        .carousel-indicators button.active {
            background-color: var(--wl-accent);
            border-color: var(--wl-accent);
            transform: scale(1.2);
        }
        
        .carousel-indicators button:hover {
            background-color: rgba(255, 255, 255, 0.8);
            border-color: rgba(255, 255, 255, 0.8);
        }
        
        .carousel-control-prev,
        .carousel-control-next {
            width: 60px;
            height: 60px;
            background-color: rgba(0, 0, 0, 0.4);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .carousel-control-prev {
            left: 30px;
        }
        
        .carousel-control-next {
            right: 30px;
        }
        
        .carousel-control-prev:hover,
        .carousel-control-next:hover {
            background-color: rgba(0, 0, 0, 0.6);
            border-color: rgba(255, 255, 255, 0.8);
            transform: translateY(-50%) scale(1.1);
        }
        
        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            width: 20px;
            height: 20px;
        }
        
        .hero-image {
            animation: float 4s ease-in-out infinite;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
        }
        
        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
            }
            50% { 
                transform: translateY(-25px) rotate(2deg); 
            }
        }
        
        .hero-content h1 {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
            animation: slideInLeft 1s ease-out;
        }
        
        .hero-content p {
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            animation: slideInLeft 1s ease-out 0.2s both;
        }

        .home-why-section {
            position: relative;
            background:
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 35%, #f8fafc 100%);
            overflow: hidden;
        }

        .home-why-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 12% 20%, rgba(30, 58, 138, 0.06) 0%, transparent 45%),
                radial-gradient(circle at 88% 70%, rgba(20, 184, 166, 0.08) 0%, transparent 42%);
            pointer-events: none;
        }

        .home-why-section .container {
            position: relative;
            z-index: 1;
        }

        .home-eyebrow {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #4338ca;
        }

        .home-section-heading {
            font-size: clamp(1.65rem, 4vw, 2.25rem);
            font-weight: 800;
            color: var(--wl-primary);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .home-lead {
            font-size: 1.05rem;
            line-height: 1.65;
            max-width: 36rem;
            margin-left: auto;
            margin-right: auto;
        }

        .home-feature-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.75rem 1.5rem;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
            transition: border-color 0.2s ease, box-shadow 0.25s ease, transform 0.2s ease;
            border-top: 3px solid transparent;
        }

        .home-feature-card:hover {
            transform: translateY(-4px);
            border-color: #c7d2fe;
            box-shadow: 0 16px 40px rgba(30, 58, 138, 0.12);
        }

        .row.g-4 > div:nth-child(1) .home-feature-card {
            border-top-color: #1e3a8a;
        }

        .row.g-4 > div:nth-child(2) .home-feature-card {
            border-top-color: #0d9488;
        }

        .row.g-4 > div:nth-child(3) .home-feature-card {
            border-top-color: #4f46e5;
        }

        .home-feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 1.15rem;
        }

        .home-feature-icon--seeker {
            background: #e0e7ff;
            color: #3730a3;
        }

        .home-feature-icon--employer {
            background: #ccfbf1;
            color: #0f766e;
        }

        .home-feature-icon--match {
            background: #fef3c7;
            color: #b45309;
        }

        .home-feature-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--wl-ink);
            margin-bottom: 0.65rem;
        }

        .home-feature-text {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .home-feature-bullets {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
            color: #475569;
        }

        .home-feature-bullets li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }

        .home-feature-bullets li:last-child {
            margin-bottom: 0;
        }

        .home-feature-bullets i {
            color: #059669;
            font-size: 0.7rem;
        }

        .home-stats-section {
            background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
            padding-top: 2.5rem !important;
            padding-bottom: 2.5rem !important;
        }

        .home-stats-panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .home-stat-cell {
            padding: 1.75rem 1rem;
            text-align: center;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            height: 100%;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: background 0.2s ease;
        }

        @media (min-width: 992px) {
            .home-stat-cell {
                border-bottom: none;
            }
            .row.g-0 > div:last-child .home-stat-cell {
                border-right: none;
            }
        }

        @media (max-width: 991.98px) {
            .col-6:nth-child(2n) .home-stat-cell {
                border-right: none;
            }
            .row.g-0 > div:nth-last-child(-n+2) .home-stat-cell {
                border-bottom: none;
            }
        }

        .home-stat-cell:hover {
            background: #fafbff;
        }

        .home-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .home-stat-icon--jobs { background: #dbeafe; color: #1d4ed8; }
        .home-stat-icon--companies { background: #e0e7ff; color: #4338ca; }
        .home-stat-icon--seekers { background: #ccfbf1; color: #0f766e; }
        .home-stat-icon--apps { background: #cffafe; color: #0e7490; }

        .home-stat-value {
            font-size: clamp(1.75rem, 4vw, 2.35rem);
            font-weight: 800;
            color: var(--wl-primary);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .home-stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
        }

        .home-footer {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 45%, #172554 100%);
            color: rgba(255, 255, 255, 0.92);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .home-footer-logo {
            height: 44px;
            width: auto;
            max-width: 240px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.96);
            padding: 8px 14px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        }

        .home-footer-tagline {
            font-size: 0.95rem;
            color: rgba(226, 232, 240, 0.85);
            max-width: 22rem;
        }

        .home-footer-nav-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.55);
        }

        .home-footer-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
        }

        .home-footer-nav a {
            color: rgba(255, 255, 255, 0.88);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .home-footer-nav a:hover {
            color: #5eead4;
        }

        .home-footer-copy {
            font-size: 0.85rem;
            color: rgba(226, 232, 240, 0.75);
        }

        .home-footer-note {
            font-size: 0.75rem;
            color: rgba(148, 163, 184, 0.9);
        }
        
        .hero-content .btn {
            animation: slideInUp 1s ease-out 0.4s both;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .hero-content .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-content {
                padding: 2rem 1.5rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .carousel-control-prev,
            .carousel-control-next {
                width: 50px;
                height: 50px;
            }
            
            .carousel-control-prev {
                left: 15px;
            }
            
            .carousel-control-next {
                right: 15px;
            }
        }
    </style>
    <script>
        // Enhanced carousel functionality with 5-second auto-rotation
        document.addEventListener('DOMContentLoaded', function() {
            const carouselElement = document.querySelector('#heroSlider');
            
            // Debug: Log carousel element found
            console.log('Carousel element found:', carouselElement);
            
            if (!carouselElement) {
                console.error('Carousel element not found!');
                return;
            }
            
            // Wait a bit for Bootstrap to load
            setTimeout(function() {
                // Initialize carousel with 5-second interval
                const carousel = new bootstrap.Carousel(carouselElement, {
                    interval: 5000,
                    wrap: true,
                    touch: true,
                    keyboard: true,
                    ride: 'carousel'
                });
                
                // Debug: Log carousel initialization
                console.log('Carousel initialized with 5-second interval');
                
                // Start the carousel immediately
                carousel.cycle();
                
                // Store carousel reference for manual control
                window.heroCarousel = carousel;
            }, 100);
            
            // Debug: Check if carousel is cycling
            setTimeout(() => {
                console.log('Carousel should be cycling now');
                console.log('Active slide:', carouselElement.querySelector('.carousel-item.active'));
            }, 1000);
            
            // Add smooth transitions and animations
            carouselElement.addEventListener('slide.bs.carousel', function (e) {
                console.log('Slide changed to:', e.to);
                const nextItem = e.relatedTarget;
                
                // Update slide counter
                const currentSlideElement = document.getElementById('currentSlide');
                if (currentSlideElement) {
                    currentSlideElement.textContent = e.to + 1;
                }
                
                // Reset and restart animations for new slide
                const heroContent = nextItem.querySelector('.hero-content');
                if (heroContent) {
                    // Reset animation
                    heroContent.style.animation = 'none';
                    heroContent.offsetHeight; // Trigger reflow
                    
                    // Restart animations with slight delay
                    setTimeout(() => {
                        heroContent.style.animation = null;
                        
                        // Add entrance animation
                        const h1 = heroContent.querySelector('h1');
                        const p = heroContent.querySelector('p');
                        const buttons = heroContent.querySelectorAll('.btn');
                        
                        if (h1) {
                            h1.style.animation = 'slideInLeft 1s ease-out';
                        }
                        if (p) {
                            p.style.animation = 'slideInLeft 1s ease-out 0.2s both';
                        }
                        if (buttons.length > 0) {
                            buttons.forEach((btn, index) => {
                                btn.style.animation = `slideInUp 1s ease-out ${0.4 + (index * 0.1)}s both`;
                            });
                        }
                    }, 50);
                }
            });
            
            // Pause on hover for better user experience
            carouselElement.addEventListener('mouseenter', function() {
                if (window.heroCarousel) {
                    window.heroCarousel.pause();
                }
            });
            
            carouselElement.addEventListener('mouseleave', function() {
                if (window.heroCarousel) {
                    window.heroCarousel.cycle();
                }
            });
            
            // Ensure carousel starts even if there are issues
            setTimeout(function() {
                if (!carouselElement.querySelector('.carousel-item.active')) {
                    console.log('Carousel not active, forcing cycle');
                    if (window.heroCarousel) {
                        window.heroCarousel.cycle();
                    }
                }
            }, 100);
            
            // Force start the carousel after a short delay
            setTimeout(function() {
                console.log('Forcing carousel to start');
                if (window.heroCarousel) {
                    window.heroCarousel.cycle();
                }
            }, 500);
            
            // Manual timer as backup (every 5 seconds)
            let currentSlide = 0;
            const totalSlides = carouselElement.querySelectorAll('.carousel-item').length;
            console.log('Total slides found:', totalSlides);
            
            // Start manual rotation after 3 seconds to ensure page is loaded
            setTimeout(function() {
                setInterval(function() {
                    currentSlide = (currentSlide + 1) % totalSlides;
                    console.log('Manual slide change to:', currentSlide);
                    if (window.heroCarousel) {
                        window.heroCarousel.to(currentSlide);
                    }
                    
                    // Update slide counter
                    const currentSlideElement = document.getElementById('currentSlide');
                    if (currentSlideElement) {
                        currentSlideElement.textContent = currentSlide + 1;
                    }
                }, 5000);
            }, 3000);
            
            // Add click handlers for indicators
            const indicators = carouselElement.querySelectorAll('.carousel-indicators button');
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', function() {
                    if (window.heroCarousel) {
                        window.heroCarousel.to(index);
                        currentSlide = index;
                        
                        // Update slide counter
                        const currentSlideElement = document.getElementById('currentSlide');
                        if (currentSlideElement) {
                            currentSlideElement.textContent = index + 1;
                        }
                    }
                });
            });
            
            // Add keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft' && window.heroCarousel) {
                    window.heroCarousel.prev();
                    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                    
                    // Update slide counter
                    const currentSlideElement = document.getElementById('currentSlide');
                    if (currentSlideElement) {
                        currentSlideElement.textContent = currentSlide + 1;
                    }
                } else if (e.key === 'ArrowRight' && window.heroCarousel) {
                    window.heroCarousel.next();
                    currentSlide = (currentSlide + 1) % totalSlides;
                    
                    // Update slide counter
                    const currentSlideElement = document.getElementById('currentSlide');
                    if (currentSlideElement) {
                        currentSlideElement.textContent = currentSlide + 1;
                    }
                }
            });
        });
    </script>
</body>
</html>
