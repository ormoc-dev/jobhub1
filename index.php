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
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="worklink.jpg" alt="WORKLINK" class="logo-img me-2" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover;">
                WORKLINK
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
    <section class="py-5 section-soft">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col">
                    <h2 class="fw-bold section-title">Why Choose WORKLINK?</h2>
                    <p class="text-muted section-subtitle">Your gateway to career success</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm feature-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-bubble icon-primary mb-3">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h5 class="card-title">For Job Seekers</h5>
                            <p class="card-text">Browse thousands of job opportunities, apply easily, and track your applications.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm feature-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-bubble icon-secondary mb-3">
                                <i class="fas fa-building"></i>
                            </div>
                            <h5 class="card-title">For Employers</h5>
                            <p class="card-text">Post jobs, manage applications, and find the perfect candidates for your company.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm feature-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-bubble icon-accent mb-3">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h5 class="card-title">Perfect Match</h5>
                            <p class="card-text">Our platform connects the right talent with the right opportunities.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5 stats-section">
        <div class="container">
            <div class="row text-center g-4">
                <?php
                // Get statistics
                $stmt = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'");
                $activeJobs = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'");
                $activeCompanies = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM employee_profiles");
                $totalEmployees = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM job_applications");
                $totalApplications = $stmt->fetchColumn();
                ?>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h3 class="display-4 fw-bold text-primary"><?php echo $activeJobs; ?></h3>
                        <p class="text-muted">Active Jobs</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h3 class="display-4 fw-bold text-primary"><?php echo $activeCompanies; ?></h3>
                        <p class="text-muted">Companies</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h3 class="display-4 fw-bold text-warning"><?php echo $totalEmployees; ?></h3>
                        <p class="text-muted">Job Seekers</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h3 class="display-4 fw-bold text-info"><?php echo $totalApplications; ?></h3>
                        <p class="text-muted">Applications</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-briefcase me-2"></i>WORKLINK</h5>
                    <p class="text-muted">Connecting talent with opportunity since 2025.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2025 WORKLINK. All rights reserved.</p>
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

        .section-soft {
            background: var(--wl-soft);
        }

        .section-title {
            color: var(--wl-ink);
        }

        .section-subtitle {
            max-width: 560px;
            margin: 0 auto;
        }

        .feature-card {
            border-radius: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: var(--wl-card);
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }

        .icon-bubble {
            width: 70px;
            height: 70px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            font-size: 28px;
            color: #ffffff;
            box-shadow: 0 12px 20px rgba(15, 23, 42, 0.15);
        }

        .icon-primary {
            background: linear-gradient(135deg, var(--wl-primary), var(--wl-primary-dark));
        }

        .icon-secondary {
            background: linear-gradient(135deg, #38bdf8, #0ea5e9);
        }

        .icon-accent {
            background: linear-gradient(135deg, var(--wl-accent), #f97316);
        }

        .stats-section {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.12), rgba(29, 78, 216, 0.08));
        }

        .stat-card {
            background: var(--wl-card);
            border-radius: 18px;
            padding: 2rem 1.5rem;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }

        .footer-dark {
            background: #0b1220;
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
