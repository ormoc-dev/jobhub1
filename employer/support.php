<?php
include '../config.php';
requireRole('employer');

// Update current session activity
if (isset($_SESSION['user_id'])) {
    updateSessionActivity($_SESSION['user_id']);
}

// Handle contact form submission
$contactSuccess = '';
$contactError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_contact'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $contactError = 'Please fill in all required fields.';
    } else {
        // Here you can save to database or send email
        // For now, we'll just show success message
        $contactSuccess = 'Thank you for contacting us! We will get back to you within 24-48 hours.';
        
        // Optional: Save to database
        try {
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, name, email, subject, message, priority, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
            $stmt->execute([$_SESSION['user_id'], $name, $email, $subject, $message, $priority]);
        } catch (Exception $e) {
            // Table might not exist, that's okay for now
        }
        
        // Clear form
        $_POST = [];
    }
}

// Get user info for pre-filling form
$stmt = $pdo->prepare("SELECT u.username, u.email, c.company_name FROM users u LEFT JOIN companies c ON u.id = c.user_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userInfo = $stmt->fetch();
$defaultName = $userInfo['company_name'] ?? $userInfo['username'] ?? '';
$defaultEmail = $userInfo['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .help-center-hero {
            background: linear-gradient(135deg, #0D6EFD 0%, #3b82f6 50%, #60a5fa 100%);
            padding: 60px 0;
            color: white;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(13, 110, 253, 0.3);
        }
        
        .help-center-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .help-center-hero p {
            font-size: 1.2rem;
            opacity: 0.95;
        }
        
        .help-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
        }
        
        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .help-card-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0D6EFD 0%, #3b82f6 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: white;
            font-size: 2rem;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .help-card h3 {
            color: #1e3a8a;
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .help-card p {
            color: #64748b;
            line-height: 1.7;
        }
        
        .faq-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #0D6EFD;
        }
        
        .accordion-item {
            border: none;
            margin-bottom: 15px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .accordion-button {
            background: #f8f9fa;
            color: #1e3a8a;
            font-weight: 600;
            padding: 20px 25px;
            border: none;
            box-shadow: none;
        }
        
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #0D6EFD 0%, #3b82f6 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .accordion-body {
            padding: 25px;
            background: white;
            color: #475569;
            line-height: 1.8;
        }
        
        .contact-form-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .form-label {
            color: #1e3a8a;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0D6EFD;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #0D6EFD 0%, #3b82f6 100%);
            border: none;
            color: white;
            padding: 12px 40px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
            background: linear-gradient(135deg, #0b5ed7 0%, #2563eb 100%);
        }
        
        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .priority-low {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }
        
        .priority-high {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success-custom {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-danger-custom {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 30px;
        }
        
        .search-box input {
            padding-left: 50px;
        }
        
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .help-center-hero h1 {
                font-size: 2rem;
            }
            
            .help-center-hero p {
                font-size: 1rem;
            }
            
            .faq-section, .contact-form-section {
                padding: 25px;
            }
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <!-- Hero Section -->
        <div class="help-center-hero text-center">
            <div class="container">
                <i class="fas fa-headset fa-3x mb-4" style="opacity: 0.9;"></i>
                <h1>Help Center</h1>
                <p>Find answers to your questions or get in touch with our support team</p>
            </div>
        </div>

        <!-- Quick Help Cards -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3>Frequently Asked Questions</h3>
                    <p>Browse through our comprehensive FAQ section to find quick answers to common questions about using WORKLINK.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="help-card">
                    <div class="help-card-icon" style="background: linear-gradient(135deg, #10b981 0%, #34d399 100%);">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Contact Support</h3>
                    <p>Can't find what you're looking for? Reach out to our support team and we'll get back to you within 24-48 hours.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="help-card">
                    <div class="help-card-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Documentation</h3>
                    <p>Access detailed guides and tutorials to help you make the most out of WORKLINK's features.</p>
                </div>
            </div>
        </div>

        <!-- FAQs Section -->
        <div class="faq-section">
            <h2 class="section-title">
                <i class="fas fa-question-circle me-2"></i>Frequently Asked Questions
            </h2>
            
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" id="faqSearch" placeholder="Search FAQs...">
            </div>
            
            <div class="accordion" id="faqAccordion">
                <!-- Account & Profile -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading1">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                            <i class="fas fa-user-circle me-2"></i>How do I update my company profile?
                        </button>
                    </h2>
                    <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            To update your company profile, navigate to <strong>Company Profile</strong> from the sidebar menu. You can edit your company information, upload a logo, update contact details, and modify your company description. All changes are saved automatically when you click the "Update Profile" button.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading2">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                            <i class="fas fa-lock me-2"></i>How do I change my password?
                        </button>
                    </h2>
                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You can change your password by going to <strong>Settings</strong> in the sidebar. In the Security section, enter your current password and your new password (twice for confirmation). Make sure your new password is at least 8 characters long and contains a mix of letters, numbers, and special characters.
                        </div>
                    </div>
                </div>

                <!-- Job Posting -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading3">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                            <i class="fas fa-briefcase me-2"></i>How do I post a new job?
                        </button>
                    </h2>
                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Click on <strong>Post Job</strong> in the sidebar menu. Fill in all required fields including job title, description, requirements, location, salary range, and job type. You can also specify required documents for applicants. Once submitted, your job posting will be reviewed and activated within 24 hours.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading4">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                            <i class="fas fa-edit me-2"></i>Can I edit or delete a job posting after it's published?
                        </button>
                    </h2>
                    <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, you can edit your job postings at any time. Go to <strong>My Jobs</strong> and click on the job you want to edit. You can modify all details except the job ID. To delete a job, click the delete button. Note that deleting a job will also remove all associated applications.
                        </div>
                    </div>
                </div>

                <!-- Applications -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading5">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                            <i class="fas fa-users me-2"></i>How do I review job applications?
                        </button>
                    </h2>
                    <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Navigate to <strong>Applications</strong> from the sidebar. You'll see all applications for your job postings. Click on any application to view the candidate's profile, resume, and required documents. You can accept, reject, or mark applications as pending. You can also use the <strong>Best Fit Candidates</strong> feature to find candidates that match your job requirements.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading6">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6">
                            <i class="fas fa-file-alt me-2"></i>What documents can I require from applicants?
                        </button>
                    </h2>
                    <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You can require various documents including resume, cover letter, educational certificates, professional licenses, work experience certificates, NBI clearance, police clearance, medical certificates, and more. The available documents depend on the job category you select. You can specify required documents when posting a job.
                        </div>
                    </div>
                </div>

                <!-- Matching & Reports -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading7">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7">
                            <i class="fas fa-handshake me-2"></i>How does the matching system work?
                        </button>
                    </h2>
                    <div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Our matching system analyzes job requirements and candidate profiles to find the best matches. It considers skills, experience, education, location, and other factors. You can view matching results in the <strong>Matching Result</strong> section. The system provides a compatibility score to help you identify top candidates.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading8">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8">
                            <i class="fas fa-chart-bar me-2"></i>What reports are available?
                        </button>
                    </h2>
                    <div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The Reports section provides insights into your job postings and applications. You can view statistics on total jobs, active jobs, applications received, application status breakdown, and monthly trends. Reports help you track your hiring performance and make data-driven decisions.
                        </div>
                    </div>
                </div>

                <!-- Messages & Communication -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading9">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse9">
                            <i class="fas fa-envelope me-2"></i>How do I message candidates?
                        </button>
                    </h2>
                    <div id="collapse9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Go to <strong>Messages</strong> in the sidebar to access your inbox. You can send messages to candidates who have applied to your jobs. Click on a conversation to view message history and send new messages. All messages are stored securely and can be accessed anytime.
                        </div>
                    </div>
                </div>

                <!-- Verification -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading10">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse10">
                            <i class="fas fa-check-circle me-2"></i>What is document verification?
                        </button>
                    </h2>
                    <div id="collapse10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Document verification allows you to verify the authenticity of documents submitted by candidates. You can view verification history in the <strong>Verification History</strong> section. Verified documents are marked with a checkmark, giving you confidence in candidate credentials.
                        </div>
                    </div>
                </div>

                <!-- Technical Support -->
                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading11">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse11">
                            <i class="fas fa-cog me-2"></i>I'm experiencing technical issues. What should I do?
                        </button>
                    </h2>
                    <div id="collapse11" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            If you encounter any technical issues, please use the contact form below to report the problem. Include details about what you were trying to do, what error messages you saw, and your browser information. Our technical team will investigate and respond within 24-48 hours.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h2 class="accordion-header" id="heading12">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse12">
                            <i class="fas fa-shield-alt me-2"></i>Is my data secure?
                        </button>
                    </h2>
                    <div id="collapse12" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, we take data security seriously. All data is encrypted and stored securely. We follow industry best practices for data protection and privacy. Your company information and candidate data are only accessible to authorized personnel. You can enable two-factor authentication in Settings for additional security.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support Section -->
        <div class="contact-form-section">
            <h2 class="section-title">
                <i class="fas fa-headset me-2"></i>Contact Support
            </h2>
            
            <?php if ($contactSuccess): ?>
                <div class="alert alert-success-custom alert-custom">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($contactSuccess); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($contactError): ?>
                <div class="alert alert-danger-custom alert-custom">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($contactError); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($defaultName); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($defaultEmail); ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject" name="subject" 
                               placeholder="e.g., Need help with job posting" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="message" name="message" rows="6" 
                              placeholder="Please describe your issue or question in detail..." required></textarea>
                </div>
                
                <div class="text-end">
                    <button type="submit" name="submit_contact" class="btn btn-submit">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FAQ Search Functionality
        document.getElementById('faqSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.accordion-button').textContent.toLowerCase();
                const answer = item.querySelector('.accordion-body').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
