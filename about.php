<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="wl-theme about-page">
    <?php include 'config.php'; ?>
    
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
                        <a class="nav-link" href="jobs.php">Available Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About Us</a>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-3 fw-bold text-white mb-3">
                        About <span style="color: #f59e0b;">WORKLINK</span>
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Your trusted partner in connecting talent with opportunity across the Philippines.
                    </p>
                    <div class="hero-actions d-flex align-items-center gap-3">
                        <a href="register.php" class="btn btn-light text-primary fw-semibold px-4">
                            Get Started
                        </a>
                        <span class="text-white-50 small">Find jobs and hire faster with WORKLINK</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card about-highlight border-0 shadow-lg">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="about-icon">
                                    <i class="fas fa-handshake"></i>
                                </span>
                                <h5 class="fw-bold mb-0">What is WORKLINK?</h5>
                            </div>
                            <p class="text-secondary mb-3">
                                WORKLINK is an innovative employment platform designed to bridge the gap between jobseekers and employers through intelligent matching technology. Our mission is to make the job search and hiring process faster, smarter, and more efficient by connecting the right talent with the right opportunities.
                            </p>
                            <p class="text-secondary mb-3">
                                We leverage advanced algorithms and data-driven insights to match candidates' skills, experiences, and preferences with employers' requirements, ensuring high-quality hires and better career opportunities. Whether you are a jobseeker looking for your dream job or an employer seeking top talent, WORKLINK provides a seamless, user-friendly, and secure environment to meet your employment needs.
                            </p>
                            <p class="text-secondary mb-0">
                                At WORKLINK, we believe that everyone deserves the right opportunity to grow and succeed. Our platform is committed to empowering individuals, supporting businesses, and transforming the recruitment landscape with innovation, intelligence, and integrity.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card dashboard-card h-100">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="fas fa-bullseye fa-3x" style="color: #2563eb;"></i>
                            </div>
                            <h3 class="text-center mb-3">Our Mission</h3>
                            <p class="text-secondary text-center">
                                To bridge the gap between talented job seekers and forward-thinking employers, 
                                creating meaningful connections that drive career growth and business success.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card dashboard-card h-100">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="fas fa-eye fa-3x" style="color: #1d4ed8;"></i>
                            </div>
                            <h3 class="text-center mb-3">Our Vision</h3>
                            <p class="text-secondary text-center">
                                To be the leading job portal in the Philippines, empowering individuals to 
                                achieve their career aspirations while helping companies build exceptional teams.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="section-muted py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Values</h2>
                <p class="text-secondary">The principles that guide everything we do</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-users fa-3x mb-3" style="color: #2563eb;"></i>
                        <h5>People First</h5>
                        <p class="text-secondary">We prioritize the needs and success of both job seekers and employers in every decision we make.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-shield-alt fa-3x mb-3" style="color: #1d4ed8;"></i>
                        <h5>Trust & Integrity</h5>
                        <p class="text-secondary">We maintain the highest standards of honesty and transparency in all our interactions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-rocket fa-3x mb-3" style="color: #f59e0b;"></i>
                        <h5>Innovation</h5>
                        <p class="text-secondary">We continuously improve our platform to provide the best possible experience for our users.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">How WORKLINK Works</h2>
                <p class="text-secondary">Simple steps to connect talent with opportunity</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body p-4">
                            <div class="step-badge text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                                <span class="fw-bold fs-4">1</span>
                            </div>
                            <h5>Create Your Profile</h5>
                            <p class="text-secondary">Job seekers create detailed profiles, while employers set up company pages to showcase their brand.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body p-4">
                            <div class="step-badge text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                                <span class="fw-bold fs-4">2</span>
                            </div>
                            <h5>Search & Apply</h5>
                            <p class="text-secondary">Browse thousands of job opportunities and apply with just a few clicks. Employers can review applications easily.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body p-4">
                            <div class="step-badge accent text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                                <span class="fw-bold fs-4">3</span>
                            </div>
                            <h5>Connect & Succeed</h5>
                            <p class="text-secondary">Build meaningful connections through our messaging system and take the next step in your career journey.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics -->
    <section class="stats-section py-5">
        <div class="container">
            <div class="row text-center text-white g-4">
                <?php
                // Get real statistics
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
                    <h3 class="display-4 fw-bold"><?php echo $activeJobs; ?>+</h3>
                    <p>Active Job Postings</p>
                </div>
                <div class="col-md-3">
                    <h3 class="display-4 fw-bold"><?php echo $activeCompanies; ?>+</h3>
                    <p>Partner Companies</p>
                </div>
                <div class="col-md-3">
                    <h3 class="display-4 fw-bold"><?php echo $totalEmployees; ?>+</h3>
                    <p>Registered Job Seekers</p>
                </div>
                <div class="col-md-3">
                    <h3 class="display-4 fw-bold"><?php echo $totalApplications; ?>+</h3>
                    <p>Successful Connections</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5">
        <div class="container text-center">
            <h2 class="fw-bold mb-4">Ready to Get Started?</h2>
            <p class="lead text-secondary mb-4">Join thousands of job seekers and employers who trust WORKLINK</p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="register.php" class="btn btn-lg cta-primary">
                    <i class="fas fa-user-plus me-2"></i>Join as Job Seeker
                </a>
                <a href="register.php" class="btn btn-lg cta-secondary">
                    <i class="fas fa-building me-2"></i>Join as Employer
                </a>
            </div>
        </div>
    </section>

    <!-- Worklink Video -->
    <section class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="about-video card border-0 shadow-sm video-compact">
                        <div class="card-body p-2">
                            <video class="w-100 rounded" controls preload="metadata">
                                <source src="worklinks.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
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
            background: #f6f8fc;
            color: var(--wl-ink);
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

        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
            padding: 120px 0 90px;
        }

        .about-highlight {
            border-radius: 18px;
            background: #ffffff;
        }

        .hero-actions .btn {
            border-radius: 999px;
        }

        .about-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(37, 99, 235, 0.1);
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .about-page h1,
        .about-page h2,
        .about-page h3,
        .about-page h5,
        .about-page p {
            text-transform: none;
            letter-spacing: normal;
        }

        .about-page p {
            line-height: 1.7;
        }

        .section-muted {
            background: #eef2f7;
        }

        .step-badge {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        .step-badge.accent {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stats-section {
            background: linear-gradient(135deg, #1d4ed8 0%, #0f172a 100%);
            color: #fff;
        }

        .cta-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .cta-secondary {
            background: #1e3a8a;
            border-color: #1e3a8a;
            color: #fff;
        }

        .about-video {
            border-radius: 16px;
            background: #ffffff;
        }

        .video-compact {
            max-width: 520px;
            margin: 0 auto;
        }
    </style>
</body>
</html>
