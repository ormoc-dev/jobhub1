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
    <link href="css/minimal.css" rel="stylesheet">
    <style>
        .support-page {
            --support-border: #e2e8f0;
            --support-muted: #64748b;
            --support-heading: #1e293b;
        }

        .support-hero {
            background: #fff;
            border: 1px solid var(--support-border);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            padding: 1.35rem 1.25rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .support-hero__icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 10px;
            background: #f1f5f9;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }

        .support-hero__title {
            font-size: 1.45rem;
            font-weight: 600;
            color: var(--support-heading);
            margin-bottom: 0.35rem;
        }

        .support-hero__lead {
            color: var(--support-muted);
            font-size: 0.9375rem;
            max-width: 34rem;
            margin: 0 auto;
            line-height: 1.5;
        }

        .support-help-card {
            background: #fff;
            border: 1px solid var(--support-border);
            border-radius: 12px;
            padding: 1.2rem 1.25rem;
            height: 100%;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        a.support-help-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        a.support-help-link:hover .support-help-card {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        .support-help-card__icon {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 8px;
            background: #f1f5f9;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }

        .support-help-card__title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--support-heading);
            margin-bottom: 0.4rem;
        }

        .support-help-card__text {
            font-size: 0.875rem;
            color: var(--support-muted);
            line-height: 1.55;
            margin-bottom: 0;
        }

        .support-help-card__hint {
            font-size: 0.7rem;
            color: #94a3b8;
            margin: 0.55rem 0 0;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .support-panel {
            background: #fff;
            border: 1px solid var(--support-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        @media (min-width: 768px) {
            .support-panel {
                padding: 1.65rem 1.85rem;
            }
        }

        .support-section-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--support-heading);
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--support-border);
        }

        .support-page .search-wrap {
            position: relative;
            margin-bottom: 1rem;
        }

        .support-page .search-wrap input {
            padding-left: 2.65rem;
            border-radius: 8px;
            border: 1px solid var(--support-border);
            font-size: 0.9375rem;
        }

        .support-page .search-wrap i {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.88rem;
        }

        .support-page .accordion-item {
            border: 1px solid var(--support-border);
            border-radius: 8px !important;
            margin-bottom: 0.4rem;
            overflow: hidden;
            background: #fff;
        }

        .support-page .accordion-button {
            background: #fff;
            color: var(--support-heading);
            font-weight: 500;
            font-size: 0.9375rem;
            padding: 0.75rem 0.95rem;
            box-shadow: none;
        }

        .support-page .accordion-button:not(.collapsed) {
            background: #f8fafc;
            color: var(--support-heading);
            box-shadow: none;
        }

        .support-page .accordion-button::after {
            opacity: 0.4;
        }

        .support-page .accordion-button:focus {
            box-shadow: none;
            border-color: transparent;
        }

        .support-page .accordion-body {
            padding: 0.9rem 0.95rem;
            font-size: 0.9375rem;
            color: #475569;
            line-height: 1.62;
            background: #fff;
            border-top: 1px solid #f1f5f9;
        }

        .support-page .form-label {
            color: var(--support-heading);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .support-page .form-control,
        .support-page .form-select {
            border: 1px solid var(--support-border);
            border-radius: 8px;
            font-size: 0.9375rem;
        }

        .support-page .form-control:focus,
        .support-page .form-select:focus {
            border-color: #94a3b8;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.22);
        }

        .support-alert {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            margin-bottom: 1.15rem;
            font-size: 0.9375rem;
        }

        .support-alert--ok {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .support-alert--err {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .support-hero__title {
                font-size: 1.3rem;
            }

            .support-panel {
                padding: 1.2rem;
            }
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content support-page">
        <header class="support-hero">
            <div class="support-hero__icon" aria-hidden="true">
                <i class="fas fa-headset"></i>
            </div>
            <h1 class="support-hero__title">Help Center</h1>
            <p class="support-hero__lead">Find answers below or open a support request. Everything here uses the same calm layout as the rest of your dashboard.</p>
        </header>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-md-4">
                <a class="support-help-link" href="#support-faq">
                    <div class="support-help-card">
                        <div class="support-help-card__icon" aria-hidden="true">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h2 class="support-help-card__title">Frequently asked questions</h2>
                        <p class="support-help-card__text">Browse common questions about profiles, jobs, applications, and more.</p>
                        <p class="support-help-card__hint">Go to FAQ</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a class="support-help-link" href="#support-contact">
                    <div class="support-help-card">
                        <div class="support-help-card__icon" aria-hidden="true">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h2 class="support-help-card__title">Contact support</h2>
                        <p class="support-help-card__text">Send a message to the team. We typically reply within 24–48 hours.</p>
                        <p class="support-help-card__hint">Open form</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a class="support-help-link" href="reports.php">
                    <div class="support-help-card">
                        <div class="support-help-card__icon" aria-hidden="true">
                            <i class="fas fa-book"></i>
                        </div>
                        <h2 class="support-help-card__title">Reports &amp; data</h2>
                        <p class="support-help-card__text">Review hiring analytics and exports from your dashboard reports area.</p>
                        <p class="support-help-card__hint">View reports</p>
                    </div>
                </a>
            </div>
        </div>

        <section class="support-panel" id="support-faq">
            <h2 class="support-section-title mb-3">Frequently asked questions</h2>

            <div class="search-wrap">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" class="form-control" id="faqSearch" placeholder="Search questions and answers…" autocomplete="off">
            </div>

            <div class="accordion" id="faqAccordion">
                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading1">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                            <i class="fas fa-user-circle me-2 text-secondary"></i>How do I update my company profile?
                        </button>
                    </h3>
                    <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion" aria-labelledby="heading1">
                        <div class="accordion-body">
                            To update your company profile, navigate to <strong>Company Profile</strong> from the sidebar menu. You can edit your company information, upload a logo, update contact details, and modify your company description. All changes are saved automatically when you click the "Update Profile" button.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading2">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                            <i class="fas fa-lock me-2 text-secondary"></i>How do I change my password?
                        </button>
                    </h3>
                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading2">
                        <div class="accordion-body">
                            You can change your password by going to <strong>Settings</strong> in the sidebar. In the Security section, enter your current password and your new password (twice for confirmation). Make sure your new password is at least 8 characters long and contains a mix of letters, numbers, and special characters.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading3">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                            <i class="fas fa-briefcase me-2 text-secondary"></i>How do I post a new job?
                        </button>
                    </h3>
                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading3">
                        <div class="accordion-body">
                            Click on <strong>Post Job</strong> in the sidebar menu. Fill in all required fields including job title, description, requirements, location, salary range, and job type. You can also specify required documents for applicants. Once submitted, your job posting will be reviewed and activated within 24 hours.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading4">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                            <i class="fas fa-edit me-2 text-secondary"></i>Can I edit or delete a job posting after it's published?
                        </button>
                    </h3>
                    <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading4">
                        <div class="accordion-body">
                            Yes, you can edit your job postings at any time. Go to <strong>My Jobs</strong> and click on the job you want to edit. You can modify all details except the job ID. To delete a job, click the delete button. Note that deleting a job will also remove all associated applications.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading5">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false" aria-controls="collapse5">
                            <i class="fas fa-users me-2 text-secondary"></i>How do I review job applications?
                        </button>
                    </h3>
                    <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading5">
                        <div class="accordion-body">
                            Navigate to <strong>Applications</strong> from the sidebar. You'll see all applications for your job postings. Click on any application to view the candidate's profile, resume, and required documents. You can accept, reject, or mark applications as pending. You can also use the <strong>Best Fit Candidates</strong> feature to find candidates that match your job requirements.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading6">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
                            <i class="fas fa-file-alt me-2 text-secondary"></i>What documents can I require from applicants?
                        </button>
                    </h3>
                    <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading6">
                        <div class="accordion-body">
                            You can require various documents including resume, cover letter, educational certificates, professional licenses, work experience certificates, NBI clearance, police clearance, medical certificates, and more. The available documents depend on the job category you select. You can specify required documents when posting a job.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading7">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7" aria-expanded="false" aria-controls="collapse7">
                            <i class="fas fa-handshake me-2 text-secondary"></i>How does the matching system work?
                        </button>
                    </h3>
                    <div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading7">
                        <div class="accordion-body">
                            Our matching system analyzes job requirements and candidate profiles to find the best matches. It considers skills, experience, education, location, and other factors. You can view matching results in the <strong>Matching Result</strong> section. The system provides a compatibility score to help you identify top candidates.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading8">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8" aria-expanded="false" aria-controls="collapse8">
                            <i class="fas fa-chart-bar me-2 text-secondary"></i>What reports are available?
                        </button>
                    </h3>
                    <div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading8">
                        <div class="accordion-body">
                            The Reports section provides insights into your job postings and applications. You can view statistics on total jobs, active jobs, applications received, application status breakdown, and monthly trends. Reports help you track your hiring performance and make data-driven decisions.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading9">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse9" aria-expanded="false" aria-controls="collapse9">
                            <i class="fas fa-envelope me-2 text-secondary"></i>How do I message candidates?
                        </button>
                    </h3>
                    <div id="collapse9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading9">
                        <div class="accordion-body">
                            Go to <strong>Messages</strong> in the sidebar to access your inbox. You can send messages to candidates who have applied to your jobs. Click on a conversation to view message history and send new messages. All messages are stored securely and can be accessed anytime.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading10">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse10" aria-expanded="false" aria-controls="collapse10">
                            <i class="fas fa-check-circle me-2 text-secondary"></i>What is document verification?
                        </button>
                    </h3>
                    <div id="collapse10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading10">
                        <div class="accordion-body">
                            Document verification allows you to verify the authenticity of documents submitted by candidates. You can view verification history in the <strong>Verification History</strong> section. Verified documents are marked with a checkmark, giving you confidence in candidate credentials.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading11">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse11" aria-expanded="false" aria-controls="collapse11">
                            <i class="fas fa-cog me-2 text-secondary"></i>I'm experiencing technical issues. What should I do?
                        </button>
                    </h3>
                    <div id="collapse11" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading11">
                        <div class="accordion-body">
                            If you encounter any technical issues, please use the contact form below to report the problem. Include details about what you were trying to do, what error messages you saw, and your browser information. Our technical team will investigate and respond within 24-48 hours.
                        </div>
                    </div>
                </div>

                <div class="accordion-item faq-item">
                    <h3 class="accordion-header" id="heading12">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse12" aria-expanded="false" aria-controls="collapse12">
                            <i class="fas fa-shield-alt me-2 text-secondary"></i>Is my data secure?
                        </button>
                    </h3>
                    <div id="collapse12" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" aria-labelledby="heading12">
                        <div class="accordion-body">
                            Yes, we take data security seriously. All data is encrypted and stored securely. We follow industry best practices for data protection and privacy. Your company information and candidate data are only accessible to authorized personnel. You can enable two-factor authentication in Settings for additional security.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-panel" id="support-contact">
            <h2 class="support-section-title mb-3">Contact support</h2>

            <?php if ($contactSuccess): ?>
                <div class="support-alert support-alert--ok">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($contactSuccess); ?>
                </div>
            <?php endif; ?>

            <?php if ($contactError): ?>
                <div class="support-alert support-alert--err">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($contactError); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Your name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo htmlspecialchars($defaultName); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($defaultEmail); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject" name="subject"
                               placeholder="e.g. Need help with a job posting" required>
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

                <div class="mb-3">
                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="message" name="message" rows="5"
                              placeholder="Describe your question or issue…" required></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" name="submit_contact" class="btn btn-primary px-4">
                        <i class="fas fa-paper-plane me-2"></i>Send message
                    </button>
                </div>
            </form>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('faqSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.faq-item').forEach(function(item) {
                const question = item.querySelector('.accordion-button').textContent.toLowerCase();
                const answer = item.querySelector('.accordion-body').textContent.toLowerCase();
                item.style.display = (question.includes(searchTerm) || answer.includes(searchTerm)) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
