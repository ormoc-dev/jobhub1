<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="wl-theme contact-page">
    <?php 
    include 'config.php';
    
    $message = '';
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $subject = sanitizeInput($_POST['subject']);
        $messageText = sanitizeInput($_POST['message']);
        
        if (empty($name) || empty($email) || empty($subject) || empty($messageText)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Here you would typically send an email or save to database
            $message = 'Thank you for your message! We will get back to you soon.';
            
            // Clear form data
            $_POST = array();
        }
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
                        <a class="nav-link" href="index.php">Home</a>
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
                        <a class="nav-link active" href="contact.php">Contact</a>
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
    <main class="contact-main">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center contact-hero">
                    <h1 class="display-4 fw-bold">Get in Touch</h1>
                    <p class="lead text-secondary">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4 align-items-stretch contact-shell">
                    <div class="col-lg-5">
                        <div class="h-100">
                            <div class="contact-panel-title">Contact Details</div>
                            <div class="d-grid gap-3">
                                <div class="card dashboard-card contact-info-card text-start">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3">
                                            <i class="fas fa-map-marker-alt fa-2x contact-icon"></i>
                                            <div>
                                                <h6 class="mb-1">Our Address</h6>
                                                <p class="text-secondary mb-0">
                                                    123 Business District<br>
                                                    Manila, Philippines<br>
                                                    1234
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card dashboard-card contact-info-card text-start">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3">
                                            <i class="fas fa-phone fa-2x contact-icon contact-icon-alt"></i>
                                            <div>
                                                <h6 class="mb-1">Phone Number</h6>
                                                <p class="text-secondary mb-0">
                                                    +63 (2) 123-4567<br>
                                                    +63 (917) 123-4567<br>
                                                    Mon-Fri: 8AM-6PM
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card dashboard-card contact-info-card text-start">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3">
                                            <i class="fas fa-envelope fa-2x contact-icon contact-icon-warn"></i>
                                            <div>
                                                <h6 class="mb-1">Email Address</h6>
                                                <p class="text-secondary mb-0">
                                                    info@worklink.com<br>
                                                    support@worklink.com<br>
                                                    partnerships@worklink.com
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="form-container contact-form h-100">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="Enter your full name" required>
                                        <div class="form-text">Your complete name</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="your.email@example.com" required>
                                        <div class="form-text">We'll respond to this email address</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select class="form-control" name="subject" required>
                                        <option value="">Select a subject</option>
                                        <option value="General Inquiry" <?php echo ($_POST['subject'] ?? '') === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                        <option value="Technical Support" <?php echo ($_POST['subject'] ?? '') === 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                        <option value="Account Issues" <?php echo ($_POST['subject'] ?? '') === 'Account Issues' ? 'selected' : ''; ?>>Account Issues</option>
                                        <option value="Job Posting Help" <?php echo ($_POST['subject'] ?? '') === 'Job Posting Help' ? 'selected' : ''; ?>>Job Posting Help</option>
                                        <option value="Partnership" <?php echo ($_POST['subject'] ?? '') === 'Partnership' ? 'selected' : ''; ?>>Partnership Opportunities</option>
                                        <option value="Feedback" <?php echo ($_POST['subject'] ?? '') === 'Feedback' ? 'selected' : ''; ?>>Feedback & Suggestions</option>
                                    </select>
                                    <div class="form-text">Choose the most relevant topic</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" name="message" rows="6" required placeholder="Please describe your inquiry in detail..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    <div class="form-text">Provide as much detail as possible</div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-accent btn-lg px-5">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Frequently Asked Questions</h2>
                    <p class="text-secondary">Quick answers to common questions</p>
                </div>
                
                <div class="accordion contact-accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I create an account?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Click on "Sign Up" in the top navigation, choose your role (Job Seeker or Employer), fill out the registration form, and wait for admin approval.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                How long does account approval take?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Account approval typically takes 1-3 business days. You'll receive an email notification once your account is approved.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Is WORKLINK free to use?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, WORKLINK is completely free for job seekers. Employers can post jobs and manage applications at no cost.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                How do I reset my password?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Contact our support team at support@worklink.com with your username or email, and we'll help you reset your password.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <!-- Footer -->
    <footer class="footer-dark text-white py-4 mt-5">
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

        .footer-dark {
            background: #0b1220;
        }

        .contact-main {
            background: transparent;
            padding-top: 20px;
        }

        .contact-hero h1 {
            color: var(--wl-primary);
        }

        .contact-hero .text-secondary {
            color: #64748b !important;
        }

        .contact-shell {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .contact-form {
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            padding: 40px;
        }

        .contact-info-card {
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            border-radius: 15px;
        }

        .contact-icon {
            color: var(--wl-accent);
        }

        .contact-icon-alt {
            color: var(--wl-primary);
        }

        .contact-icon-warn {
            color: #f97316;
        }

        .contact-accordion .accordion-item {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            margin-bottom: 12px;
            background: #ffffff;
        }

        .contact-accordion .accordion-button {
            font-weight: 600;
            background: #f8fafc;
            color: #1f2937;
        }

        .contact-accordion .accordion-button:not(.collapsed) {
            color: #ffffff;
            background: var(--wl-primary);
            box-shadow: none;
        }

        .contact-accordion .accordion-body {
            background: #ffffff;
            color: #4b5563;
        }

        .contact-page .form-control,
        .contact-page .form-select,
        .contact-page textarea.form-control {
            background: #ffffff;
            color: #111827;
            border-color: #e5e7eb;
        }

        .contact-page .form-control:focus {
            background: #ffffff;
            color: #111827;
            border-color: var(--wl-accent);
            box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.25);
        }
    </style>
</body>
</html>
