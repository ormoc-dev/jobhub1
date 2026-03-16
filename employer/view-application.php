<?php
include '../config.php';
include '../bootstrap/config/env.php';
include 'mailer.php';
requireRole('employer');

// Get application ID from URL
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$application_id) {
    $_SESSION['error'] = 'Invalid application ID.';
    redirect('applications.php');
}

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// Get application details with related data
$stmt = $pdo->prepare("SELECT ja.*, ja.resume, ja.id_document, ja.tor_document, ja.employment_certificate, ja.seminar_certificate, ja.cover_letter, ja.certificate_of_attachment, ja.certificate_of_reports, ja.certificate_of_good_standing, ja.status, ja.interview_status,
                             jp.title as job_title, jp.location, jp.salary_range,
                             jp.description as job_description, jp.requirements as job_requirements,
                             ep.first_name, ep.last_name, ep.employee_id, ep.contact_no, ep.sex, ep.date_of_birth, 
                             ep.civil_status, ep.highest_education, ep.address, ep.place_of_birth,
                             ep.document1, ep.document2,
                             u.email, u.created_at as user_created,
                             jc.category_name
                      FROM job_applications ja
                      JOIN job_postings jp ON ja.job_id = jp.id
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      JOIN users u ON ep.user_id = u.id
                      LEFT JOIN job_categories jc ON jp.category_id = jc.id
                      WHERE ja.id = ? AND jp.company_id = ?");
$stmt->execute([$application_id, $company['id']]);
$application = $stmt->fetch();

if (!$application) {
    $_SESSION['error'] = 'Application not found or access denied.';
    redirect('applications.php');
}

// Handle status update
$message = '';
$error = '';
$sms_message = '';
$sms_error = '';
$email_message = '';
$email_error = '';

function normalizePhoneNumber($number) {
    $number = trim((string) $number);
    if ($number === '') {
        return '';
    }
    $number = preg_replace('/\s+|-|\(|\)/', '', $number);
    if (strpos($number, '+') === 0) {
        return $number;
    }
    if (strpos($number, '0') === 0) {
        return '+63' . substr($number, 1);
    }
    return $number;
}

function buildInterviewSmsMessage($application, $company, $interview_date = '', $interview_time = '', $interview_mode = '') {
    $jobseeker_name = trim($application['first_name'] . ' ' . $application['last_name']);
    $job_title = $application['job_title'] ?? 'the position';
    $company_name = $company['company_name'] ?? 'our company';
    $employer_name = trim(($company['contact_first_name'] ?? '') . ' ' . ($company['contact_last_name'] ?? ''));
    $employer_position = $company['contact_position'] ?? 'Employer';
    $employer_contact = $company['contact_number'] ?? '';

    $interview_date = $interview_date !== '' ? $interview_date : 'TBD';
    $interview_time = $interview_time !== '' ? $interview_time : 'TBD';
    $interview_mode = $interview_mode !== '' ? $interview_mode : 'TBD';

    $confirmation_line = "Please confirm your availability by replying to this message.";
    if ($employer_contact !== '') {
        $confirmation_line .= " You may also text or call {$employer_contact}.";
    }

    return "Hello {$jobseeker_name},\n\n"
        . "Good day! We have reviewed your profile and are pleased to inform you that you are shortlisted for the {$job_title} position at {$company_name}.\n\n"
        . "We would like to invite you for an interview on {$interview_date} at {$interview_time}, to be conducted via {$interview_mode}.\n\n"
        . "{$confirmation_line}\n\n"
        . "Thank you, and we look forward to speaking with you.\n\n"
        . "Best regards,\n"
        . "{$employer_name}\n"
        . "{$employer_position}\n"
        . "{$company_name}";
}

function sendIprogSmsNotification($recipient, $message_body) {
    if ($recipient === '' || $message_body === '') {
        return null;
    }

    // Format number to 639... format required by typical PH SMS gateways
    $recipient = preg_replace('/[^0-9]/', '', $recipient);
    if (strpos($recipient, '09') === 0) {
        $recipient = '63' . substr($recipient, 1);
    } elseif (strpos($recipient, '9') === 0 && strlen($recipient) === 10) {
        $recipient = '63' . $recipient;
    }

    $url = 'https://www.iprogsms.com/api/v1/sms_messages';
    
    $data = [
        'api_token' => IPROG_API_TOKEN,
        'message' => $message_body,
        'phone_number' => $recipient
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Get document verification history for this application FIRST (before POST handling)
// This ensures we have fresh data even after redirect from verify-document.php
$stmt = $pdo->prepare("SELECT dv.id, dv.application_id, 
                              TRIM(dv.document_type) as document_type, 
                              TRIM(dv.verification_status) as verification_status, 
                              dv.verification_notes, 
                              dv.verified_at, 
                              u.username as verified_by_username 
                       FROM document_verifications dv
                       JOIN users u ON dv.verified_by = u.id
                       WHERE dv.application_id = ?
                       ORDER BY dv.verified_at DESC");
$stmt->execute([$application_id]);
$verification_history = $stmt->fetchAll();

// Check for session messages (from verify-document.php redirect)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $new_status = sanitizeInput($_POST['new_status']);
        $allowed_statuses = ['pending', 'reviewed', 'accepted', 'rejected'];
        
        if (in_array($new_status, $allowed_statuses)) {
            $stmt = $pdo->prepare("UPDATE job_applications SET status = ?, reviewed_date = NOW() WHERE id = ?");
            if ($stmt->execute([$new_status, $application_id])) {
                $message = 'Application status updated successfully!';
                // Refresh the application data
                $stmt = $pdo->prepare("SELECT ja.*, ja.resume, ja.id_document, ja.tor_document, ja.employment_certificate, ja.seminar_certificate, ja.cover_letter, ja.certificate_of_attachment, ja.certificate_of_reports, ja.certificate_of_good_standing, ja.status, ja.interview_status,
                                             jp.title as job_title, jp.location, jp.salary_range,
                                             jp.description as job_description, jp.requirements as job_requirements,
                                             ep.first_name, ep.last_name, ep.employee_id, ep.contact_no, ep.sex, ep.date_of_birth, 
                                             ep.civil_status, ep.highest_education, ep.address, ep.place_of_birth,
                                             ep.document1, ep.document2,
                                             u.email, u.created_at as user_created,
                                             jc.category_name
                                      FROM job_applications ja
                                      JOIN job_postings jp ON ja.job_id = jp.id
                                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                                      JOIN users u ON ep.user_id = u.id
                                      LEFT JOIN job_categories jc ON jp.category_id = jc.id
                                      WHERE ja.id = ? AND jp.company_id = ?");
                $stmt->execute([$application_id, $company['id']]);
                $application = $stmt->fetch();
                
                // Refresh verification history after status update
                $stmt = $pdo->prepare("SELECT dv.*, u.username as verified_by_username 
                                       FROM document_verifications dv
                                       JOIN users u ON dv.verified_by = u.id
                                       WHERE dv.application_id = ?
                                       ORDER BY dv.verified_at DESC");
                $stmt->execute([$application_id]);
                $verification_history = $stmt->fetchAll();


                if ($new_status === 'accepted' || $new_status === 'reviewed' || $new_status === 'rejected') {
                    $send_sms = isset($_POST['send_sms']) && $_POST['send_sms'] == '1';
                    
                    if ($send_sms) {
                        $recipient = $application['contact_no'] ?? '';
                        $jobseeker_name = trim($application['first_name'] . ' ' . $application['last_name']);
                        $company_name = $company['company_name'] ?? 'our company';
                        $job_title = $application['job_title'] ?? 'the position';
                        
                        $sms_body = isset($_POST['custom_sms_message']) ? trim($_POST['custom_sms_message']) : '';
                        
                        if (empty($sms_body)) {
                            if ($new_status === 'accepted') {
                                $sms_body = sprintf(
                                    "Hi %s, Congratulations! your application for %s at %s has been APPROVED. Please check your account for updates.", 
                                    $jobseeker_name, 
                                    $job_title, 
                                    $company_name
                                );
                            } elseif ($new_status === 'rejected') {
                                $sms_body = sprintf(
                                    "Hi %s, Thank you for applying for %s at %s. Unfortunately, we have decided to move forward with other candidates.", 
                                    $jobseeker_name, 
                                    $job_title, 
                                    $company_name
                                );
                            } else {
                                $sms_body = sprintf(
                                    "Hi %s, We've reviewed your application for %s at %s. We'll be in touch soon for further updates.", 
                                    $jobseeker_name, 
                                    $job_title, 
                                    $company_name
                                );
                            }
                        }
                        
                        $sms_response_raw = sendIprogSmsNotification($recipient, $sms_body);
                        if ($sms_response_raw) {
                            $sms_response = json_decode($sms_response_raw, true);
                            if (isset($sms_response['status']) && strtolower($sms_response['status']) === 'success') {
                                $sms_message = "SMS notification sent successfully!";
                            } else {
                                $error_desc = $sms_response['message'] ?? 'Unknown error';
                                $sms_error = "Failed to send SMS: " . $error_desc;
                            }
                        } else {
                            $sms_error = "Failed to communicate with the SMS gateway.";
                        }
                    }

                    $send_email = isset($_POST['send_email']) && $_POST['send_email'] == '1';
                    if ($send_email) {
                        $recipient_email = $application['email'] ?? '';
                        if (!empty($recipient_email)) {
                            $subject = "Update regarding your application for " . ($application['job_title'] ?? 'the position') . " at " . ($company['company_name'] ?? 'our company');
                            
                            $custom_email = isset($_POST['custom_email_message']) ? trim($_POST['custom_email_message']) : '';
                            
                            if (!empty($custom_email)) {
                                // Convert custom message to HTML friendly format (newlines to <br>)
                                $email_body = nl2br(htmlspecialchars($custom_email));
                            } elseif ($new_status === 'accepted') {
                                $email_body = "<h3>Congratulations!</h3><p>Hi " . htmlspecialchars($application['first_name']) . ",</p><p>We are pleased to inform you that your application for <strong>" . htmlspecialchars($application['job_title']) . "</strong> at <strong>" . htmlspecialchars($company['company_name']) . "</strong> has been <strong>APPROVED</strong>.</p><p>Please log in to your account for further details and next steps.</p><p>Best regards,<br>" . htmlspecialchars($company['company_name']) . " Team</p>";
                            } elseif ($new_status === 'rejected') {
                                $email_body = "<h3>Application Update</h3><p>Hi " . htmlspecialchars($application['first_name']) . ",</p><p>Thank you for your interest in the <strong>" . htmlspecialchars($application['job_title']) . "</strong> position at <strong>" . htmlspecialchars($company['company_name']) . "</strong>.</p><p>After careful review, we have decided to move forward with other candidates at this time. We appreciate the time and effort you put into your application and wish you the best in your job search.</p><p>Best regards,<br>" . htmlspecialchars($company['company_name']) . " Team</p>";
                            } else {
                                $email_body = "<h3>Application Under Review</h3><p>Hi " . htmlspecialchars($application['first_name']) . ",</p><p>We have reviewed your application for <strong>" . htmlspecialchars($application['job_title']) . "</strong> at <strong>" . htmlspecialchars($company['company_name']) . "</strong>.</p><p>We will be in touch with you soon regarding the next steps in our hiring process.</p><p>Best regards,<br>" . htmlspecialchars($company['company_name']) . " Team</p>";
                            }

                            $email_result = sendEmailNotification($recipient_email, $subject, $email_body);
                            if ($email_result['status'] === 'success') {
                                $email_message = "Email notification sent successfully!";
                            } else {
                                $email_error = "Failed to send Email: " . $email_result['message'];
                            }
                        }
                    }
                }
                
            } else {
                $error = 'Failed to update application status.';
            }
        } else {
            $error = 'Invalid status.';
        }
    }
    
    if ($action === 'mark_as_uninterview') {
        // Verify application belongs to company
        $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                               JOIN job_postings jp ON ja.job_id = jp.id 
                               WHERE ja.id = ? AND jp.company_id = ?");
        $stmt->execute([$application_id, $company['id']]);
        $app_check = $stmt->fetch();
        
        if ($app_check) {
            // Update status to rejected and redirect to applications.php
            $stmt = $pdo->prepare("UPDATE job_applications SET status = 'rejected', reviewed_date = NOW() WHERE id = ?");
            if ($stmt->execute([$application_id])) {
                $_SESSION['message'] = 'Application moved to Rejected / Archived.';
                redirect('applications.php?status=rejected');
            } else {
                $error = 'Failed to update application status.';
            }
        } else {
            $error = 'Application not found or access denied.';
        }
    }
    
    if ($action === 'update_interview_status') {
        $new_interview_status = sanitizeInput($_POST['new_interview_status']);
        $allowed_interview_statuses = ['interviewed'];
        
        if (in_array($new_interview_status, $allowed_interview_statuses)) {
            // Get interview details from form
            $interview_date = sanitizeInput($_POST['interview_date'] ?? '');
            $interview_time = sanitizeInput($_POST['interview_time'] ?? '');
            $interview_mode = sanitizeInput($_POST['interview_mode'] ?? '');
            
            // Save interview_status AND interview details to database
            $stmt = $pdo->prepare("UPDATE job_applications SET interview_status = ?, interview_date = ?, interview_time = ?, interview_mode = ? WHERE id = ?");
            $interview_date_db = !empty($interview_date) ? $interview_date : null;
            $interview_time_db = !empty($interview_time) ? $interview_time : null;
            $interview_mode_db = !empty($interview_mode) ? $interview_mode : null;
            
            if ($stmt->execute([$new_interview_status, $interview_date_db, $interview_time_db, $interview_mode_db, $application_id])) {
                $message = 'Interview scheduled successfully!';
                // Refresh the application data
                $stmt = $pdo->prepare("SELECT ja.*, ja.resume, ja.id_document, ja.tor_document, ja.employment_certificate, ja.seminar_certificate, ja.cover_letter, ja.certificate_of_attachment, ja.certificate_of_reports, ja.certificate_of_good_standing, ja.status, ja.interview_status,
                                             jp.title as job_title, jp.location, jp.salary_range,
                                             jp.description as job_description, jp.requirements as job_requirements,
                                             ep.first_name, ep.last_name, ep.employee_id, ep.contact_no, ep.sex, ep.date_of_birth, 
                                             ep.civil_status, ep.highest_education, ep.address, ep.place_of_birth,
                                             ep.document1, ep.document2,
                                             u.email, u.created_at as user_created,
                                             jc.category_name
                                      FROM job_applications ja
                                      JOIN job_postings jp ON ja.job_id = jp.id
                                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                                      JOIN users u ON ep.user_id = u.id
                                      LEFT JOIN job_categories jc ON jp.category_id = jc.id
                                      WHERE ja.id = ? AND jp.company_id = ?");
                $stmt->execute([$application_id, $company['id']]);
                $application = $stmt->fetch();
                
                if ($new_interview_status === 'interviewed') {
                    $send_sms = isset($_POST['send_sms']) && $_POST['send_sms'] == '1';
                    
                    if ($send_sms) {
                        $recipient = $application['contact_no'] ?? '';
                        if (!empty($recipient)) {
                            $sms_body = isset($_POST['custom_sms_message']) ? trim($_POST['custom_sms_message']) : '';
                            
                            if (empty($sms_body)) {
                                $sms_body = buildInterviewSmsMessage($application, $company, $interview_date, $interview_time, $interview_mode);
                            }
                            
                            $sms_response_raw = sendIprogSmsNotification($recipient, $sms_body);
                            if ($sms_response_raw) {
                                $sms_response = json_decode($sms_response_raw, true);
                                if (isset($sms_response['status']) && strtolower($sms_response['status']) === 'success') {
                                    $sms_message = "Interview SMS sent successfully!";
                                } else {
                                    $error_desc = $sms_response['message'] ?? 'Unknown error';
                                    $sms_error = "Failed to send Interview SMS: " . $error_desc;
                                }
                            } else {
                                $sms_error = "Failed to communicate with the SMS gateway.";
                            }
                        }
                    }

                    $send_email = isset($_POST['send_email']) && $_POST['send_email'] == '1';
                    if ($send_email) {
                        $recipient_email = $application['email'] ?? '';
                        if (!empty($recipient_email)) {
                            $subject = "Interview Invitation: " . ($application['job_title'] ?? 'Position') . " at " . ($company['company_name'] ?? 'Company');
                            
                            $email_body = "<h3>Interview Invitation</h3>"
                                . "<p>Hi " . htmlspecialchars($application['first_name']) . ",</p>"
                                . "<p>We are pleased to invite you for an interview for the <strong>" . htmlspecialchars($application['job_title'] ?? 'position') . "</strong> at <strong>" . htmlspecialchars($company['company_name'] ?? 'our company') . "</strong>.</p>"
                                . "<ul>"
                                . "<li><strong>Date:</strong> " . htmlspecialchars($interview_date ?: 'To be confirmed') . "</li>"
                                . "<li><strong>Time:</strong> " . htmlspecialchars($interview_time ?: 'To be confirmed') . "</li>"
                                . "<li><strong>Mode:</strong> " . htmlspecialchars($interview_mode ?: 'To be confirmed') . "</li>"
                                . "</ul>"
                                . "<p>Please confirm your availability as soon as possible. We look forward to meeting you!</p>"
                                . "<p>Best regards,<br>" . htmlspecialchars($company['company_name'] ?? 'HR Team') . "</p>";

                            $email_result = sendEmailNotification($recipient_email, $subject, $email_body);
                            if ($email_result['status'] === 'success') {
                                $email_message = "Interview Email sent successfully!";
                            } else {
                                $email_error = "Failed to send Interview Email: " . $email_result['message'];
                            }
                        }
                    }
                }

                // Refresh verification history after interview status update
                $stmt = $pdo->prepare("SELECT dv.*, u.username as verified_by_username 
                                       FROM document_verifications dv
                                       JOIN users u ON dv.verified_by = u.id
                                       WHERE dv.application_id = ?
                                       ORDER BY dv.verified_at DESC");
                $stmt->execute([$application_id]);
                $verification_history = $stmt->fetchAll();
            } else {
                $error = 'Failed to update interview status.';
            }
        } else {
            $error = 'Invalid interview status.';
        }
    }
}

// Verification history is already fetched at the top of the file
// (before POST handling to ensure fresh data after redirects)

// Check if documents are verified
$doc1_verified = false;
$doc2_verified = false;
$doc1_verification_status = null;
$doc2_verification_status = null;

foreach ($verification_history as $verify) {
    if ($verify['document_type'] === 'document1') {
        $doc1_verified = true;
        $doc1_verification_status = $verify['verification_status'];
    }
    if ($verify['document_type'] === 'document2') {
        $doc2_verified = true;
        $doc2_verification_status = $verify['verification_status'];
    }
}

// Calculate age
$age = '';
if ($application['date_of_birth']) {
    $birthDate = new DateTime($application['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}

// Status styling
$statusClass = '';
$statusIcon = '';
switch($application['status']) {
    case 'pending':
        $statusClass = 'bg-warning text-dark';
        $statusIcon = 'clock';
        break;
    case 'reviewed':
        $statusClass = 'bg-info text-white';
        $statusIcon = 'eye';
        break;
    case 'accepted':
        $statusClass = 'bg-success text-white';
        $statusIcon = 'check';
        break;
        case 'rejected':
            $statusClass = 'bg-danger text-white';
            $statusIcon = 'times';
            break;
    }
    
    $interviewStatusClass = '';
    $interviewStatusIcon = '';
    if ($application['interview_status']) {
        if ($application['interview_status'] === 'interviewed') {
            $interviewStatusClass = 'bg-success text-white';
            $interviewStatusIcon = 'check-circle';
        } else {
            $interviewStatusClass = 'bg-secondary text-white';
            $interviewStatusIcon = 'calendar-times';
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .application-view {
            background: #f3f6fb;
        }

        .application-view .main-content {
            padding-top: 1.5rem;
        }

        .application-view .page-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .application-view .page-header h1,
        .application-view .page-header h5,
        .application-view .page-header .h2 {
            color: #fff;
        }

        .application-view .page-header .btn {
            border-color: rgba(255, 255, 255, 0.6);
            color: #fff;
        }

        .application-view .page-header .btn:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        .application-view .card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        .application-view .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
            font-weight: 600;
        }

        .application-view .card-body p {
            color: #0f172a;
        }

        .application-view .badge {
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
        }

        .application-view .table thead th {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }

        .application-view .alert {
            border-radius: 12px;
        }

        .application-view .application-sidebar {
            position: sticky;
            top: 1.5rem;
        }

        .application-view .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
        }

        .application-view .btn-success {
            background: #16a34a;
            border-color: #16a34a;
        }

        .application-view .btn-danger {
            background: #dc2626;
            border-color: #dc2626;
        }

        .application-view .btn-info {
            background: #0ea5e9;
            border-color: #0ea5e9;
            color: #fff;
        }

        @media (max-width: 991.98px) {
            .application-view .application-sidebar {
                position: static;
            }
        }
    </style>
</head>
<body class="employer-layout application-view">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center page-header">
                    <h1 class="h2">
                        <i class="fas fa-file-alt me-2"></i>Application Details
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="applications.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Applications
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- SMS Toast Notification Container -->
                <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
                    <?php if ($sms_message): ?>
                    <div id="smsSuccessToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-envelope-open-text me-2"></i><?php echo htmlspecialchars($sms_message); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($sms_error): ?>
                    <div id="smsErrorToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($sms_error); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($email_message): ?>
                    <div id="emailSuccessToast" class="toast align-items-center text-bg-info text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-paper-plane me-2"></i><?php echo htmlspecialchars($email_message); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($email_error): ?>
                    <div id="emailErrorToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($email_error); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Application Overview -->
                <div class="row g-4">
                    <div class="col-md-8 application-main">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>Applicant Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Full Name:</label>
                                            <p><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Email:</label>
                                            <p>
                                                <i class="fas fa-envelope me-1"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>">
                                                    <?php echo htmlspecialchars($application['email']); ?>
                                                </a>
                                            </p>
                                        </div>

                                        <?php if ($application['contact_no']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Phone Number:</label>
                                            <p>
                                                <i class="fas fa-phone me-1"></i>
                                                <a href="tel:<?php echo htmlspecialchars($application['contact_no']); ?>">
                                                    <?php echo htmlspecialchars($application['contact_no']); ?>
                                                </a>
                                            </p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($application['employee_id']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Employee ID:</label>
                                            <p><?php echo htmlspecialchars($application['employee_id']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <?php if ($age): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Age:</label>
                                            <p><?php echo $age; ?> years old</p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($application['sex']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Gender:</label>
                                            <p><?php echo htmlspecialchars($application['sex']); ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($application['civil_status']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Civil Status:</label>
                                            <p><?php echo htmlspecialchars($application['civil_status']); ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($application['highest_education']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Education:</label>
                                            <p><span class="badge bg-secondary"><?php echo htmlspecialchars($application['highest_education']); ?></span></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($application['address']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Address:</label>
                                    <p><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($application['address']); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if ($application['place_of_birth']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Place of Birth:</label>
                                    <p><?php echo htmlspecialchars($application['place_of_birth']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Job Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-briefcase me-2"></i>Applied Position
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Job Title:</label>
                                            <p><?php echo htmlspecialchars($application['job_title']); ?></p>
                                        </div>

                                        <?php if ($application['category_name']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Category:</label>
                                            <p><span class="badge bg-primary text-white"><?php echo htmlspecialchars($application['category_name']); ?></span></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <?php if ($application['location']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Location:</label>
                                            <p><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($application['location']); ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($application['salary_range']): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Salary Range:</label>
                                            <p><i class="fas fa-peso-sign me-1"></i><?php echo htmlspecialchars($application['salary_range']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cover Letter (as file) -->
                        <?php if ($application['cover_letter']): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-text me-2"></i>Cover Letter
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $cover_letter_path = '../' . $application['cover_letter'];
                                $cover_letter_exists = file_exists(__DIR__ . '/../' . $application['cover_letter']);
                                $cover_letter_ext = strtolower(pathinfo($application['cover_letter'], PATHINFO_EXTENSION));
                                $is_image_cl = in_array($cover_letter_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                ?>
                                <?php if ($cover_letter_exists && $is_image_cl): ?>
                                    <img src="<?php echo htmlspecialchars($cover_letter_path); ?>" alt="Cover Letter" class="img-fluid mb-3" style="max-height: 600px;">
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($cover_letter_path); ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-eye me-1"></i>View Cover Letter
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Employee Submitted Documents - Only show if documents exist -->
                        <?php 
                        // Check if documents actually exist on server
                        $has_valid_docs = false;
                        if (!empty($application['document1']) && file_exists(__DIR__ . '/../' . $application['document1'])) {
                            $has_valid_docs = true;
                        }
                        if (!empty($application['document2']) && file_exists(__DIR__ . '/../' . $application['document2'])) {
                            $has_valid_docs = true;
                        }
                        ?>
                        <?php if ($has_valid_docs): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-folder-open me-2"></i>Downloadable Documents
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if ($application['document1']): 
                                        // Get file paths - database stores relative path like 'uploads/employees/filename.jpg'
                                        $doc1_relative_path = $application['document1'];
                                        $doc1_url = '../' . htmlspecialchars($doc1_relative_path); // Relative from employer folder: ../uploads/employees/file.jpg
                                        
                                        // Check if file exists (use absolute path from root)
                                        $doc1_file_exists = file_exists(__DIR__ . '/../' . $doc1_relative_path);
                                        
                                        $doc1_ext = strtolower(pathinfo($application['document1'], PATHINFO_EXTENSION));
                                        $is_pdf1 = ($doc1_ext === 'pdf');
                                        $is_image1 = in_array($doc1_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card <?php echo $doc1_verified ? ($doc1_verification_status === 'verified' ? 'border-success' : 'border-danger') : 'border-primary'; ?>">
                                            <div class="card-body text-center">
                                                <?php if ($doc1_file_exists && $is_image1): ?>
                                                    <img src="<?php echo $doc1_url; ?>" alt="Document 1" class="img-thumbnail mb-3" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                                <?php elseif ($is_pdf1): ?>
                                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file fa-3x text-muted mb-3"></i>
                                                <?php endif; ?>
                                                
                                                <h6 class="mb-2">Document 1 (Resume/CV)</h6>
                                                <?php if (!$doc1_file_exists): ?>
                                                    <div class="alert alert-warning py-2 px-3 mb-2">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <small>File not found on server</small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($doc1_verified): ?>
                                                    <p class="mb-2">
                                                        <span class="badge <?php echo $doc1_verification_status === 'verified' ? 'bg-success' : 'bg-danger'; ?>">
                                                            <i class="fas fa-<?php echo $doc1_verification_status === 'verified' ? 'check' : 'times'; ?> me-1"></i>
                                                            <?php echo ucfirst($doc1_verification_status); ?>
                                                        </span>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="mb-2 text-muted"><small>Not verified yet</small></p>
                                                <?php endif; ?>
                                                
                                                <div class="btn-group-vertical d-grid gap-2">
                                                    <?php if ($doc1_file_exists): ?>
                                                        <a href="<?php echo $doc1_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>View Document
                                                        </a>
                                                        <?php if (!$doc1_verified || $doc1_verification_status === 'rejected'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verifyModal1">
                                                                <i class="fas fa-check-circle me-1"></i>Verify Document
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($doc1_verified && $doc1_verification_status === 'verified'): ?>
                                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal1">
                                                                <i class="fas fa-times-circle me-1"></i>Reject Document
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                            <i class="fas fa-ban me-1"></i>File Not Available
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($application['document2']): 
                                        // Get file paths - database stores relative path like 'uploads/employees/filename.jpg'
                                        $doc2_relative_path = $application['document2'];
                                        $doc2_url = '../' . htmlspecialchars($doc2_relative_path); // Relative from employer folder: ../uploads/employees/file.jpg
                                        
                                        // Check if file exists (use absolute path from root)
                                        $doc2_file_exists = file_exists(__DIR__ . '/../' . $doc2_relative_path);
                                        
                                        $doc2_ext = strtolower(pathinfo($application['document2'], PATHINFO_EXTENSION));
                                        $is_pdf2 = ($doc2_ext === 'pdf');
                                        $is_image2 = in_array($doc2_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card <?php echo $doc2_verified ? ($doc2_verification_status === 'verified' ? 'border-success' : 'border-danger') : 'border-primary'; ?>">
                                            <div class="card-body text-center">
                                                <?php if ($doc2_file_exists && $is_image2): ?>
                                                    <img src="<?php echo $doc2_url; ?>" alt="Document 2" class="img-thumbnail mb-3" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                                <?php elseif ($is_pdf2): ?>
                                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file fa-3x text-muted mb-3"></i>
                                                <?php endif; ?>
                                                
                                                <h6 class="mb-2">Document 2 (Other)</h6>
                                                <?php if (!$doc2_file_exists): ?>
                                                    <div class="alert alert-warning py-2 px-3 mb-2">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <small>File not found on server</small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($doc2_verified): ?>
                                                    <p class="mb-2">
                                                        <span class="badge <?php echo $doc2_verification_status === 'verified' ? 'bg-success' : 'bg-danger'; ?>">
                                                            <i class="fas fa-<?php echo $doc2_verification_status === 'verified' ? 'check' : 'times'; ?> me-1"></i>
                                                            <?php echo ucfirst($doc2_verification_status); ?>
                                                        </span>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="mb-2 text-muted"><small>Not verified yet</small></p>
                                                <?php endif; ?>
                                                
                                                <div class="btn-group-vertical d-grid gap-2">
                                                    <?php if ($doc2_file_exists): ?>
                                                        <a href="<?php echo $doc2_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>View Document
                                                        </a>
                                                        <?php if (!$doc2_verified || $doc2_verification_status === 'rejected'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verifyModal2">
                                                                <i class="fas fa-check-circle me-1"></i>Verify Document
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($doc2_verified && $doc2_verification_status === 'verified'): ?>
                                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal2">
                                                                <i class="fas fa-times-circle me-1"></i>Reject Document
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                            <i class="fas fa-ban me-1"></i>File Not Available
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Verification History -->
                                <?php if (!empty($verification_history)): ?>
                                <hr>
                                <h6 class="mb-3"><i class="fas fa-history me-2"></i>Verification History</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th>Status</th>
                                                <th>Verified By</th>
                                                <th>Date</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Helper function to get document type display name
                                            $getDocTypeName = function($doc_type) {
                                                if (empty($doc_type)) return 'Unknown Document';
                                                $doc_type_normalized = trim(strtolower($doc_type));
                                                return match($doc_type_normalized) {
                                                    'resume' => 'Resume',
                                                    'id_document' => 'ID Document',
                                                    'document1' => 'Document 1 (Resume/CV)',
                                                    'document2' => 'Document 2 (Other)',
                                                    'tor_document' => 'TOR Document',
                                                    'employment_certificate' => 'Employment Certificate',
                                                    'seminar_certificate' => 'Seminar Certificate',
                                                    'cover_letter' => 'Cover Letter',
                                                    'certificate_of_attachment' => 'Certificate of Attachment',
                                                    'certificate_of_reports' => 'Certificate of Reports',
                                                    'certificate_of_good_standing' => 'Certificate of Good Standing',
                                                    default => ucfirst(str_replace('_', ' ', trim($doc_type)))
                                                };
                                            };
                                            
                                            foreach ($verification_history as $verify): 
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $doc_type_raw = $verify['document_type'] ?? '';
                                                    $doc_type_display = $getDocTypeName($doc_type_raw);
                                                    ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-file-alt me-1"></i>
                                                        <?php echo htmlspecialchars($doc_type_display); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $verify['verification_status'] === 'verified' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <i class="fas fa-<?php echo $verify['verification_status'] === 'verified' ? 'check' : 'times'; ?> me-1"></i>
                                                        <?php echo ucfirst($verify['verification_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($verify['verified_by_username']); ?></td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($verify['verified_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($verify['verification_notes']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Application Submitted Documents -->
                        <?php if ($application['resume'] || $application['tor_document'] || $application['id_document'] || $application['employment_certificate'] || $application['seminar_certificate'] || $application['certificate_of_attachment'] || $application['certificate_of_reports'] || $application['certificate_of_good_standing']): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-certificate me-2"></i>Downloadable Documents
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    // Helper function to display and verify documents
                                    $displayApplicationDoc = function($doc_path, $doc_name, $doc_type, $app_id, $border_class) use ($verification_history, $application_id) {
                                        if (empty($doc_path)) return '';
                                        
                                        $doc_exists = file_exists(__DIR__ . '/../' . $doc_path);
                                        $full_path = '../' . $doc_path;
                                        $doc_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                        $is_image = in_array($doc_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                        $is_pdf = ($doc_ext === 'pdf');
                                        
                                        // Check verification status - get the most recent verification for this document type
                                        $doc_verified = false;
                                        $doc_verification_status = null;
                                        // Since verification_history is ordered by verified_at DESC, first match is most recent
                                        $doc_type_normalized = trim(strtolower($doc_type));
                                        
                                        // Debug: Check if verification_history is populated
                                        if (!empty($verification_history) && is_array($verification_history)) {
                                            foreach ($verification_history as $verify) {
                                                // Use strict comparison and ensure both are trimmed strings
                                                $verify_doc_type = trim(strtolower($verify['document_type'] ?? ''));
                                                if ($verify_doc_type === $doc_type_normalized) {
                                                    $doc_verified = true;
                                                    $doc_verification_status = trim($verify['verification_status'] ?? '');
                                                    // Get the most recent verification (first one found since ordered DESC)
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        $output = '<div class="col-md-4 mb-3">
                                            <div class="card ' . ($doc_verified ? ($doc_verification_status === 'verified' ? 'border-success' : 'border-danger') : 'border-primary') . '">
                                                <div class="card-body text-center">';
                                        
                                        if ($is_image && $doc_exists) {
                                            $output .= '<img src="' . htmlspecialchars($full_path) . '" alt="' . htmlspecialchars($doc_name) . '" class="img-thumbnail mb-3" style="max-width: 150px; max-height: 150px; object-fit: cover;">';
                                        } elseif ($is_pdf) {
                                            $output .= '<i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>';
                                        } else {
                                            $output .= '<i class="fas fa-file fa-3x text-muted mb-3"></i>';
                                        }
                                        
                                        $output .= '<h6 class="mb-2">' . htmlspecialchars($doc_name) . '</h6>';
                                        
                                        if (!$doc_exists) {
                                            $output .= '<div class="alert alert-warning py-2 px-3 mb-2">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <small>File not found on server</small>
                                            </div>';
                                        }
                                        
                                        if ($doc_verified && !empty($doc_verification_status)) {
                                            $output .= '<p class="mb-2">
                                                <span class="badge ' . ($doc_verification_status === 'verified' ? 'bg-success' : 'bg-danger') . '">
                                                    <i class="fas fa-' . ($doc_verification_status === 'verified' ? 'check' : 'times') . ' me-1"></i>
                                                    ' . ucfirst($doc_verification_status) . '
                                                </span>
                                            </p>';
                                        } else {
                                            $output .= '<p class="mb-2 text-muted"><small>Not verified yet</small></p>';
                                        }
                                        
                                        $output .= '<div class="btn-group-vertical d-grid gap-2">';
                                        if ($doc_exists) {
                                            $output .= '<a href="' . htmlspecialchars($full_path) . '" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>View Document
                                            </a>';
                                            if (!$doc_verified || ($doc_verified && $doc_verification_status === 'rejected')) {
                                                $modal_id = 'verifyModal' . $doc_type; // Modal IDs match: verifyModaltor_document, verifyModalemployment_certificate, etc.
                                                $output .= '<button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modal_id) . '">
                                                    <i class="fas fa-check-circle me-1"></i>Verify Document
                                                </button>';
                                            }
                                            if ($doc_verified && $doc_verification_status === 'verified') {
                                                $modal_id = 'rejectModal' . $doc_type; // Modal IDs match: rejectModaltor_document, rejectModalemployment_certificate, etc.
                                                $output .= '<button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modal_id) . '">
                                                    <i class="fas fa-times-circle me-1"></i>Reject Document
                                                </button>';
                                            }
                                        } else {
                                            $output .= '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="fas fa-ban me-1"></i>File Not Available
                                            </button>';
                                        }
                                        $output .= '</div></div></div></div>';
                                        
                                        return $output;
                                    };
                                    
                                    // Display documents in order as per requirements
                                    if ($application['resume']) {
                                        echo $displayApplicationDoc($application['resume'], 'Resume / Curriculum Vitae (CV)', 'resume', $application_id, 'border-primary');
                                    }
                                    if ($application['tor_document']) {
                                        echo $displayApplicationDoc($application['tor_document'], 'Bachelor\'s Degree Diploma & Transcript of Records (TOR)', 'tor_document', $application_id, 'border-info');
                                    }
                                    if ($application['id_document']) {
                                        echo $displayApplicationDoc($application['id_document'], 'Government-issued ID', 'id_document', $application_id, 'border-warning');
                                    }
                                    if ($application['employment_certificate']) {
                                        echo $displayApplicationDoc($application['employment_certificate'], 'Certificate(s) of Employment from previous employers', 'employment_certificate', $application_id, 'border-warning');
                                    }
                                    if ($application['seminar_certificate']) {
                                        echo $displayApplicationDoc($application['seminar_certificate'], 'Training or MS Office Certificates', 'seminar_certificate', $application_id, 'border-dark');
                                    }
                                    if ($application['certificate_of_attachment']) {
                                        echo $displayApplicationDoc($application['certificate_of_attachment'], 'Reference Letters', 'certificate_of_attachment', $application_id, 'border-info');
                                    }
                                    if ($application['certificate_of_reports']) {
                                        echo $displayApplicationDoc($application['certificate_of_reports'], 'Portfolio or Work Samples', 'certificate_of_reports', $application_id, 'border-warning');
                                    }
                                    if ($application['certificate_of_good_standing']) {
                                        echo $displayApplicationDoc($application['certificate_of_good_standing'], 'Medical Certificate', 'certificate_of_good_standing', $application_id, 'border-success');
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-md-4 application-sidebar">
                        <!-- Status Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Application Status
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <span class="badge <?php echo $statusClass; ?> fs-6 mb-3">
                                    <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                    <?php echo ucfirst($application['status']); ?>
                                </span>
                                
                                <p class="mb-2"><strong>Applied Date:</strong></p>
                                <p><?php echo date('M j, Y g:i A', strtotime($application['applied_date'])); ?></p>
                                
                                <p class="mb-2"><strong>Time Ago:</strong></p>
                                <p><?php echo timeAgo($application['applied_date']); ?></p>
                            </div>
                        </div>

                        <!-- Actions Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Mark as Reviewed -->
                                <?php if ($application['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#reviewApplicationModal">
                                    <i class="fas fa-eye me-1"></i>Mark as Reviewed
                                </button>
                                <?php endif; ?>

                                <!-- Approve Application -->
                                <?php if ($application['status'] !== 'accepted'): ?>
                                <button type="button" class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#approveApplicationModal">
                                    <i class="fas fa-check me-1"></i>Approve Application
                                </button>
                                <?php endif; ?>

                                <!-- Reject Application -->
                                <?php if ($application['status'] !== 'rejected'): ?>
                                <button type="button" class="btn btn-danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#rejectApplicationModal">
                                    <i class="fas fa-times me-1"></i>Reject Application
                                </button>
                                <?php endif; ?>

                                <!-- Interview Status -->
                                <hr class="my-3">
                                <h6 class="mb-2"><i class="fas fa-user-check me-1"></i>Interview Status</h6>

                                <!-- Mark as Uninterview -->
                                <?php if ($application['interview_status'] !== 'uninterview'): ?>
                                <form method="POST" class="mb-2" action="" onsubmit="this.action = window.location.href;">
                                    <input type="hidden" name="action" value="mark_as_uninterview">
                                    <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                    <button type="submit" class="btn btn-secondary w-100">
                                        <i class="fas fa-calendar-times me-1"></i>Mark as Uninterview
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Mark as Interviewed -->
                                <?php if ($application['interview_status'] !== 'interviewed'): ?>
                                <form method="POST" class="mb-2 form-mark-interviewed" action="" onsubmit="this.action = window.location.href;">
                                    <input type="hidden" name="action" value="update_interview_status">
                                    <input type="hidden" name="new_interview_status" value="interviewed">
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Interview Date <span class="text-danger">*</span></label>
                                        <input type="date" name="interview_date" class="form-control form-control-sm interview-date-input" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Interview Time <span class="text-danger">*</span></label>
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <select class="form-select form-select-sm interview-hour" required>
                                                    <option value="">Hour</option>
                                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <select class="form-select form-select-sm interview-minute" required>
                                                    <option value="">Min</option>
                                                    <?php for($i = 0; $i <= 59; $i++): ?>
                                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <select class="form-select form-select-sm interview-ampm" required>
                                                    <option value="">AM/PM</option>
                                                    <option value="AM">AM</option>
                                                    <option value="PM">PM</option>
                                                </select>
                                            </div>
                                        </div>
                                        <input type="hidden" name="interview_time" class="interview-time-hidden">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Interview Mode <span class="text-danger">*</span></label>
                                        <input type="text" name="interview_mode" class="form-control form-control-sm interview-mode-input" placeholder="Online / On-site" required>
                                    </div>
                                    
                                    <hr>
                                    <h6 class="small fw-bold">Notification Options</h6>
                                     <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="sendEmailApproval" name="send_email" value="1" checked>
                            <label class="form-check-label" for="sendEmailApproval">
                                Send Email Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive an email about their approved application.</small>
                        </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="sendSmsInterview" name="send_sms" value="1" checked onchange="document.getElementById('interviewSmsTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                                        <label class="form-check-label small" for="sendSmsInterview">
                                            Send SMS Invitation
                                        </label>
                                    </div>
                                    <div class="mb-3" id="interviewSmsTemplateContainer">
                                        <label class="form-label small fw-bold mt-2">Customize SMS Message</label>
                                        
                                        <select id="interviewSmsTemplateSelector" class="form-select form-select-sm mb-2" onchange="updateInterviewSmsTextarea(this.value)">
                                            <option value="default">Template: Default</option>
                                            <option value="formal">Template: Formal</option>
                                            <option value="casual">Template: Casual</option>
                                        </select>

                                        <?php
                                        $jobseeker_name = trim($application['first_name'] . ' ' . $application['last_name']);
                                        $company_name = $company['company_name'] ?? 'our company';
                                        $job_title = $application['job_title'] ?? 'the position';
                                        
                                        $interview_default_sms = "Hi {$jobseeker_name}, you are invited for an interview for {$job_title} at {$company_name}. Please check your account for schedule details.";
                                        $interview_formal_sms = "Dear {$jobseeker_name}, we would like to invite you to an interview for the {$job_title} position at {$company_name}. Please log in for details.";
                                        $interview_casual_sms = "Hey {$jobseeker_name}! We'd love to chat with you about the {$job_title} role at {$company_name}. Check your account for the interview schedule!";
                                        ?>
                                        
                                        <textarea name="custom_sms_message" id="interviewCustomSmsMessage" class="form-control form-control-sm" rows="5"><?php echo htmlspecialchars($interview_default_sms); ?></textarea>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">You can freely edit the message above before sending.</small>

                                        <div class="mt-2 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-info" id="btnGenerateAiInterviewMessage">
                                                <i class="fas fa-magic me-1"></i>Generate AI Invite
                                            </button>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100 btn-mark-interviewed" disabled>
                                        <i class="fas fa-check-circle me-1"></i>Mark as Interviewed
                                    </button>
                                </form>
                                <?php endif; ?>

                                <script>
                                    (function() {
                                        function bindInterviewTime(form) {
                                            var hr = form.querySelector('.interview-hour, [name="interview_hour"]');
                                            var min = form.querySelector('.interview-minute, [name="interview_minute"]');
                                            var ampm = form.querySelector('.interview-ampm, [name="interview_ampm"]');
                                            var hidden = form.querySelector('.interview-time-hidden, [name="interview_time"]');
                                            var dateInput = form.querySelector('.interview-date-input, [name="interview_date"]');
                                            var modeInput = form.querySelector('.interview-mode-input, [name="interview_mode"]');
                                            var submitBtn = form.querySelector('.btn-mark-interviewed');
                                            
                                            if (!hr || !min || !ampm || !hidden) return;
                                            
                                            function updateTime() {
                                                if (hr.value && min.value && ampm.value)
                                                    hidden.value = hr.value + ':' + min.value + ' ' + ampm.value;
                                            }
                                            
                                            function checkFields() {
                                                updateTime();
                                                if (submitBtn) {
                                                    var allFilled = dateInput && dateInput.value && 
                                                                    hr.value && min.value && ampm.value && 
                                                                    modeInput && modeInput.value.trim();
                                                    submitBtn.disabled = !allFilled;
                                                }
                                            }
                                            
                                            hr.addEventListener('change', checkFields);
                                            min.addEventListener('change', checkFields);
                                            ampm.addEventListener('change', checkFields);
                                            if (dateInput) dateInput.addEventListener('change', checkFields);
                                            if (modeInput) modeInput.addEventListener('input', checkFields);
                                            form.addEventListener('submit', updateTime);
                                            
                                            // Initial check
                                            checkFields();
                                        }
                                        document.querySelectorAll('form').forEach(bindInterviewTime);

                                        // AI generation logic for interview
                                        function updateInterviewSmsTextarea(template) {
                                            const textarea = document.getElementById('interviewCustomSmsMessage');
                                            if (template === 'default') {
                                                textarea.value = <?php echo json_encode($interview_default_sms); ?>;
                                            } else if (template === 'formal') {
                                                textarea.value = <?php echo json_encode($interview_formal_sms); ?>;
                                            } else if (template === 'casual') {
                                                textarea.value = <?php echo json_encode($interview_casual_sms); ?>;
                                            }
                                        }

                                        const btnGenerateAiInterview = document.getElementById('btnGenerateAiInterviewMessage');
                                        if (btnGenerateAiInterview) {
                                            btnGenerateAiInterview.addEventListener('click', async function() {
                                                const btn = this;
                                                const textarea = document.getElementById('interviewCustomSmsMessage');
                                                const form = btn.closest('form');
                                                
                                                // Get current values from the form
                                                const dateInput = form.querySelector('.interview-date-input').value;
                                                const timeInput = form.querySelector('.interview-time-hidden').value;
                                                const modeInput = form.querySelector('.interview-mode-input').value;
                                                
                                                if (!dateInput || !timeInput || !modeInput) {
                                                    alert('Please fill out the interview date, time, and mode before generating an AI message.');
                                                    return;
                                                }
                                                
                                                const originalText = btn.innerHTML;
                                                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
                                                btn.disabled = true;

                                                try {
                                                    const response = await fetch('generate_ai_sms.php', {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                        },
                                                        body: JSON.stringify({
                                                            application_id: <?php echo $application_id; ?>,
                                                            jobseeker_name: <?php echo json_encode($jobseeker_name); ?>,
                                                            job_title: <?php echo json_encode($job_title); ?>,
                                                            company_name: <?php echo json_encode($company_name); ?>,
                                                            status: 'interviewed',
                                                            interview_date: dateInput,
                                                            interview_time: timeInput,
                                                            interview_mode: modeInput
                                                        })
                                                    });

                                                    const data = await response.json();
                                                    
                                                    if (data.success) {
                                                        textarea.value = data.message;
                                                    } else {
                                                        alert('Failed to generate AI message: ' + (data.error || 'Unknown error'));
                                                    }
                                                } catch (error) {
                                                    console.error('Error generating AI text:', error);
                                                    alert('An error occurred while communicating with the AI server.');
                                                } finally {
                                                    btn.innerHTML = originalText;
                                                    btn.disabled = false;
                                                }
                                            });
                                        }
                                    })();
                                </script>

                                <!-- Application Documents -->
                                <?php if ($application['cover_letter'] || $application['resume'] || $application['tor_document'] || $application['id_document'] || $application['employment_certificate'] || $application['seminar_certificate'] || $application['certificate_of_attachment'] || $application['certificate_of_reports'] || $application['certificate_of_good_standing']): ?>
                                <hr>
                                <h6 class="mb-2"><i class="fas fa-folder me-1"></i>Downloadable Documents:</h6>
                                <?php if ($application['resume']): ?>
                                <a href="../<?php echo htmlspecialchars($application['resume']); ?>" 
                                   target="_blank" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-file-pdf me-1"></i>Resume / CV
                                </a>
                                <?php endif; ?>
                                <?php if ($application['tor_document']): ?>
                                <a href="../<?php echo htmlspecialchars($application['tor_document']); ?>" 
                                   target="_blank" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-file-alt me-1"></i>Bachelor's Diploma & TOR
                                </a>
                                <?php endif; ?>
                                <?php if ($application['id_document']): ?>
                                <a href="../<?php echo htmlspecialchars($application['id_document']); ?>" 
                                   target="_blank" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-id-card me-1"></i>Government-issued ID
                                </a>
                                <?php endif; ?>
                                <?php if ($application['employment_certificate']): ?>
                                <a href="../<?php echo htmlspecialchars($application['employment_certificate']); ?>" 
                                   target="_blank" class="btn btn-warning w-100 mb-2">
                                    <i class="fas fa-certificate me-1"></i>Certificate(s) of Employment
                                </a>
                                <?php endif; ?>
                                <?php if ($application['seminar_certificate']): ?>
                                <a href="../<?php echo htmlspecialchars($application['seminar_certificate']); ?>" 
                                   target="_blank" class="btn btn-dark w-100 mb-2">
                                    <i class="fas fa-certificate me-1"></i>Training / MS Office Certificates
                                </a>
                                <?php endif; ?>
                                <?php if ($application['certificate_of_attachment']): ?>
                                <a href="../<?php echo htmlspecialchars($application['certificate_of_attachment']); ?>" 
                                   target="_blank" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-file-signature me-1"></i>Reference Letters
                                </a>
                                <?php endif; ?>
                                <?php if ($application['certificate_of_reports']): ?>
                                <a href="../<?php echo htmlspecialchars($application['certificate_of_reports']); ?>" 
                                   target="_blank" class="btn btn-warning w-100 mb-2">
                                    <i class="fas fa-briefcase me-1"></i>Portfolio / Work Samples
                                </a>
                                <?php endif; ?>
                                <?php if ($application['certificate_of_good_standing']): ?>
                                <a href="../<?php echo htmlspecialchars($application['certificate_of_good_standing']); ?>" 
                                   target="_blank" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-heartbeat me-1"></i>Medical Certificate
                                </a>
                                <?php endif; ?>
                                <?php if ($application['cover_letter']): ?>
                                <a href="../<?php echo htmlspecialchars($application['cover_letter']); ?>" 
                                   target="_blank" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-file-text me-1"></i>Cover Letter
                                </a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
      

    <!-- Verification Modals -->
    <!-- Verify Document 1 Modal -->
    <?php if ($application['document1']): ?>
    <div class="modal fade" id="verifyModal1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Document 1 (Resume/CV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="document1">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes1" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes1" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                        <p class="text-muted small">You are confirming that this document has been reviewed and is authentic.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Document 1 Modal -->
    <div class="modal fade" id="rejectModal1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Document 1 (Resume/CV)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="document1">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes1" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes1" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                        <p class="text-danger small">You are marking this document as invalid or fraudulent.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify Document 2 Modal -->
    <?php if ($application['document2']): ?>
    <div class="modal fade" id="verifyModal2" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Document 2 (Other)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="document2">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes2" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes2" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                        <p class="text-muted small">You are confirming that this document has been reviewed and is authentic.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Document 2 Modal -->
    <div class="modal fade" id="rejectModal2" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Document 2 (Other)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="document2">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes2" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes2" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                        <p class="text-danger small">You are marking this document as invalid or fraudulent.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify TOR Document Modal -->
    <?php if ($application['tor_document']): ?>
    <div class="modal fade" id="verifyModaltor_document" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify TOR Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="tor_document">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes_tor" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes_tor" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject TOR Document Modal -->
    <div class="modal fade" id="rejectModaltor_document" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject TOR Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="tor_document">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes_tor" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes_tor" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify Employment Certificate Modal -->
    <?php if ($application['employment_certificate']): ?>
    <div class="modal fade" id="verifyModalemployment_certificate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Employment Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="employment_certificate">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes_emp" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes_emp" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Employment Certificate Modal -->
    <div class="modal fade" id="rejectModalemployment_certificate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Employment Certificate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="employment_certificate">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes_emp" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes_emp" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify Seminar Certificate Modal -->
    <?php if ($application['seminar_certificate']): ?>
    <div class="modal fade" id="verifyModalseminar_certificate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Seminar Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="seminar_certificate">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes_sem" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes_sem" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Seminar Certificate Modal -->
    <div class="modal fade" id="rejectModalseminar_certificate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Seminar Certificate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="seminar_certificate">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes_sem" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes_sem" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify Certificate of Attachment Modal -->
    <?php if ($application['certificate_of_attachment']): ?>
    <div class="modal fade" id="verifyModalcertificate_of_attachment" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Certificate of Attachment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="certificate_of_attachment">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes_certattach" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes_certattach" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Certificate of Attachment Modal -->
    <div class="modal fade" id="rejectModalcertificate_of_attachment" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Certificate of Attachment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="certificate_of_attachment">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes_certattach" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes_certattach" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify Certificate of Reports Modal -->
    <?php if ($application['certificate_of_reports']): ?>
    <div class="modal fade" id="verifyModalcertificate_of_reports" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Certificate of Reports</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="certificate_of_reports">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes_certreports" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes_certreports" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Certificate of Reports Modal -->
    <div class="modal fade" id="rejectModalcertificate_of_reports" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Certificate of Reports</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="certificate_of_reports">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes_certreports" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes_certreports" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verify Certificate of Good Standing Modal -->
    <?php if ($application['certificate_of_good_standing']): ?>
    <div class="modal fade" id="verifyModalcertificate_of_good_standing" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Certificate of Good Standing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="certificate_of_good_standing">
                        <input type="hidden" name="verification_status" value="verified">
                        <div class="mb-3">
                            <label for="notes_certstanding" class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="verification_notes" id="notes_certstanding" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Verify Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Certificate of Good Standing Modal -->
    <div class="modal fade" id="rejectModalcertificate_of_good_standing" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Certificate of Good Standing</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="verify-document.php">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                        <input type="hidden" name="document_type" value="certificate_of_good_standing">
                        <input type="hidden" name="verification_status" value="rejected">
                        <div class="mb-3">
                            <label for="reject_notes_certstanding" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="verification_notes" id="reject_notes_certstanding" rows="3" placeholder="Please provide a reason for rejecting this document..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- Review Application Modal -->
    <div class="modal fade" id="reviewApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Mark Application as Reviewed</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="new_status" value="reviewed">
                        
                        <p>Are you sure you want to mark this application as reviewed? This will notify the applicant that their profile has been seen.</p>
                        
                        <hr>
                        <h6>Notification Options</h6>
                         <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="sendEmailReviewChoice" name="send_email" value="1" checked onchange="document.getElementById('reviewEmailTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="sendEmailReviewChoice">
                                Send Email Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive an email about their profile being reviewed.</small>
                        </div>
                        <div class="mb-3" id="reviewEmailTemplateContainer">
                            <label class="form-label small fw-bold">Customize Email Message (Optional)</label>
                            <textarea name="custom_email_message" id="reviewCustomEmailMessage" class="form-control form-control-sm" rows="4" placeholder="Leave empty to use the default professional template..."></textarea>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="generateAiEmail(<?php echo $application_id; ?>, 'reviewed', 'reviewCustomEmailMessage', this)">
                                    <i class="fas fa-magic me-1"></i>Generate AI Email
                                </button>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="sendSmsReview" name="send_sms" value="1" checked onchange="document.getElementById('reviewSmsTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="sendSmsReview">
                                Send SMS Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive a text message (if phone number is available).</small>
                        </div>
                        <div class="mb-3" id="reviewSmsTemplateContainer">
                            <label class="form-label small fw-bold">Customize SMS Message</label>
                            
                            <select id="reviewSmsTemplateSelector" class="form-select form-select-sm mb-2" onchange="updateReviewSmsTextarea(this.value)">
                                <option value="default">Template: Default</option>
                                <option value="formal">Template: Formal</option>
                                <option value="casual">Template: Casual</option>
                            </select>

                            <?php
                            $jobseeker_name = trim($application['first_name'] . ' ' . $application['last_name']);
                            $company_name = $company['company_name'] ?? 'our company';
                            $job_title = $application['job_title'] ?? 'the position';
                            
                            $reviewed_default_sms = "Hi {$jobseeker_name}, We've reviewed your application for {$job_title} at {$company_name}. We'll be in touch soon!";
                            $reviewed_formal_sms = "Dear {$jobseeker_name}, This is to inform you that your application for {$job_title} at {$company_name} has been reviewed by our hiring team.";
                            $reviewed_casual_sms = "Hey {$jobseeker_name}, Just a quick update: we've had a look at your application for {$job_title} at {$company_name}! Stay tuned.";
                            ?>
                            
                            <textarea name="custom_sms_message" id="reviewCustomSmsMessage" class="form-control form-control-sm" rows="5"><?php echo htmlspecialchars($reviewed_default_sms); ?></textarea>
                            <small class="text-muted d-block mt-1">You can freely edit the message above before sending.</small>

                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnGenerateAiReviewMessage">
                                    <i class="fas fa-magic me-1"></i>Generate AI Message
                                </button>
                            </div>

                            <script>
                                function updateReviewSmsTextarea(template) {
                                    const textarea = document.getElementById('reviewCustomSmsMessage');
                                    if (template === 'default') {
                                        textarea.value = <?php echo json_encode($reviewed_default_sms); ?>;
                                    } else if (template === 'formal') {
                                        textarea.value = <?php echo json_encode($reviewed_formal_sms); ?>;
                                    } else if (template === 'casual') {
                                        textarea.value = <?php echo json_encode($reviewed_casual_sms); ?>;
                                    }
                                }

                                document.getElementById('btnGenerateAiReviewMessage').addEventListener('click', async function() {
                                    const btn = this;
                                    const textarea = document.getElementById('reviewCustomSmsMessage');
                                    
                                    const originalText = btn.innerHTML;
                                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
                                    btn.disabled = true;

                                    try {
                                        const response = await fetch('generate_ai_sms.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                            },
                                            body: JSON.stringify({
                                                application_id: <?php echo $application_id; ?>,
                                                jobseeker_name: <?php echo json_encode($jobseeker_name); ?>,
                                                job_title: <?php echo json_encode($job_title); ?>,
                                                company_name: <?php echo json_encode($company_name); ?>,
                                                status: 'reviewed'
                                            })
                                        });

                                        const data = await response.json();
                                        
                                        if (data.success) {
                                            textarea.value = data.message;
                                        } else {
                                            alert('Failed to generate AI message: ' + (data.error || 'Unknown error'));
                                        }
                                    } catch (error) {
                                        console.error('Error generating AI text:', error);
                                        alert('An error occurred while communicating with the AI server.');
                                    } finally {
                                        btn.innerHTML = originalText;
                                        btn.disabled = false;
                                    }
                                });
                            </script>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-check me-1"></i>Confirm Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Application Modal -->
    <div class="modal fade" id="approveApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="new_status" value="accepted">
                        
                        <p>Are you sure you want to approve this application? The applicant will be moved to the next stage.</p>
                        
                        <hr>
                        <h6>Notification Options</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="sendEmailApproveChoice" name="send_email" value="1" checked onchange="document.getElementById('approveEmailTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="sendEmailApproveChoice">
                                Send Email Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive an email about their approved application.</small>
                        </div>
                        <div class="mb-3" id="approveEmailTemplateContainer">
                            <label class="form-label small fw-bold">Customize Email Message (Optional)</label>
                            <textarea name="custom_email_message" id="approveCustomEmailMessage" class="form-control form-control-sm" rows="4" placeholder="Leave empty to use the default professional template..."></textarea>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="generateAiEmail(<?php echo $application_id; ?>, 'accepted', 'approveCustomEmailMessage', this)">
                                    <i class="fas fa-magic me-1"></i>Generate AI Email
                                </button>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="sendSmsApproval" name="send_sms" value="1" checked onchange="document.getElementById('smsTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="sendSmsApproval">
                                Send SMS Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive a text message (if phone number is available).</small>
                        </div>
                        <div class="mb-3" id="smsTemplateContainer">
                            <label class="form-label small fw-bold">Customize SMS Message</label>
                            
                            <select id="smsTemplateSelector" class="form-select form-select-sm mb-2" onchange="updateSmsTextarea(this.value)">
                                <option value="default">Template: Default</option>
                                <option value="formal">Template: Formal</option>
                                <option value="casual">Template: Casual</option>
                            </select>

                            <?php
                            $jobseeker_name = trim($application['first_name'] . ' ' . $application['last_name']);
                            $company_name = $company['company_name'] ?? 'our company';
                            $job_title = $application['job_title'] ?? 'the position';
                            
                            $default_sms = "Hi {$jobseeker_name}, Congratulations! your application for {$job_title} at {$company_name} has been APPROVED. Please check your account for updates.";
                            $formal_sms = "Dear {$jobseeker_name}, We are pleased to inform you that your application for {$job_title} at {$company_name} has been accepted. Please check your account.";
                            $casual_sms = "Hi {$jobseeker_name}! Great news - you're accepted for the {$job_title} role at {$company_name}! Please check your account.";
                            ?>
                            
                            <textarea name="custom_sms_message" id="customSmsMessage" class="form-control form-control-sm" rows="5"><?php echo htmlspecialchars($default_sms); ?></textarea>
                            <small class="text-muted d-block mt-1">You can freely edit the message above before sending.</small>

                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnGenerateAiMessage">
                                    <i class="fas fa-magic me-1"></i>Generate AI Message
                                </button>
                            </div>

                            <script>
                                function updateSmsTextarea(template) {
                                    const textarea = document.getElementById('customSmsMessage');
                                    textarea.value = <?php echo json_encode($default_sms); ?>; // default fallback
                                    if (template === 'default') {
                                        textarea.value = <?php echo json_encode($default_sms); ?>;
                                    } else if (template === 'formal') {
                                        textarea.value = <?php echo json_encode($formal_sms); ?>;
                                    } else if (template === 'casual') {
                                        textarea.value = <?php echo json_encode($casual_sms); ?>;
                                    }
                                }

                                document.getElementById('btnGenerateAiMessage').addEventListener('click', async function() {
                                    const btn = this;
                                    const textarea = document.getElementById('customSmsMessage');
                                    
                                    const originalText = btn.innerHTML;
                                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
                                    btn.disabled = true;

                                    try {
                                        const response = await fetch('generate_ai_sms.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                            },
                                            body: JSON.stringify({
                                                application_id: <?php echo $application_id; ?>,
                                                jobseeker_name: <?php echo json_encode($jobseeker_name); ?>,
                                                job_title: <?php echo json_encode($job_title); ?>,
                                                company_name: <?php echo json_encode($company_name); ?>
                                            })
                                        });

                                        const data = await response.json();
                                        
                                        if (data.success) {
                                            textarea.value = data.message;
                                        } else {
                                            alert('Failed to generate AI message: ' + (data.error || 'Unknown error'));
                                        }
                                    } catch (error) {
                                        console.error('Error generating AI text:', error);
                                        alert('An error occurred while communicating with the AI server.');
                                    } finally {
                                        btn.innerHTML = originalText;
                                        btn.disabled = false;
                                    }
                                });
                            </script>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Application Modal -->
    <div class="modal fade" id="rejectApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="new_status" value="rejected">
                        
                        <p>Are you sure you want to reject this application? The applicant will be notified of your decision.</p>
                        
                        <hr>
                        <h6>Notification Options</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="sendEmailRejectChoice" name="send_email" value="1" checked onchange="document.getElementById('rejectEmailTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="sendEmailRejectChoice">
                                Send Email Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive an email about their rejected application.</small>
                        </div>
                        <div class="mb-3" id="rejectEmailTemplateContainer">
                            <label class="form-label small fw-bold">Customize Email Message (Optional)</label>
                            <textarea name="custom_email_message" id="rejectCustomEmailMessage" class="form-control form-control-sm" rows="4" placeholder="Leave empty to use the default professional template..."></textarea>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="generateAiEmail(<?php echo $application_id; ?>, 'rejected', 'rejectCustomEmailMessage', this)">
                                    <i class="fas fa-magic me-1"></i>Generate AI Email
                                </button>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="sendSmsReject" name="send_sms" value="1" checked onchange="document.getElementById('rejectSmsTemplateContainer').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="sendSmsReject">
                                Send SMS Notification
                            </label>
                            <small class="d-block text-muted">Applicant will receive a text message (if phone number is available).</small>
                        </div>
                        <div class="mb-3" id="rejectSmsTemplateContainer">
                            <label class="form-label small fw-bold">Customize SMS Message</label>
                            
                            <select id="rejectSmsTemplateSelector" class="form-select form-select-sm mb-2" onchange="updateRejectSmsTextarea(this.value)">
                                <option value="default">Template: Default</option>
                                <option value="formal">Template: Formal</option>
                                <option value="casual">Template: Casual</option>
                            </select>

                            <?php
                            $jobseeker_name = trim($application['first_name'] . ' ' . $application['last_name']);
                            $company_name = $company['company_name'] ?? 'our company';
                            $job_title = $application['job_title'] ?? 'the position';
                            
                            $reject_default_sms = "Hi {$jobseeker_name}, Thank you for applying for {$job_title} at {$company_name}. Unfortunately, we have decided to move forward with other candidates.";
                            $reject_formal_sms = "Dear {$jobseeker_name}, We appreciate your interest in the {$job_title} role at {$company_name}. However, we will not be proceeding with your application at this time.";
                            $reject_casual_sms = "Hi {$jobseeker_name}, Thanks for your time! We won't be moving forward with your application for {$job_title} at {$company_name}, but we wish you the best!";
                            ?>
                            
                            <textarea name="custom_sms_message" id="rejectCustomSmsMessage" class="form-control form-control-sm" rows="5"><?php echo htmlspecialchars($reject_default_sms); ?></textarea>
                            <small class="text-muted d-block mt-1">You can freely edit the message above before sending.</small>

                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnGenerateAiRejectMessage">
                                    <i class="fas fa-magic me-1"></i>Generate AI Message
                                </button>
                            </div>

                            <script>
                                function updateRejectSmsTextarea(template) {
                                    const textarea = document.getElementById('rejectCustomSmsMessage');
                                    textarea.value = <?php echo json_encode($reject_default_sms); ?>; // default fallback
                                    if (template === 'default') {
                                        textarea.value = <?php echo json_encode($reject_default_sms); ?>;
                                    } else if (template === 'formal') {
                                        textarea.value = <?php echo json_encode($reject_formal_sms); ?>;
                                    } else if (template === 'casual') {
                                        textarea.value = <?php echo json_encode($reject_casual_sms); ?>;
                                    }
                                }

                                document.getElementById('btnGenerateAiRejectMessage').addEventListener('click', async function() {
                                    const btn = this;
                                    const textarea = document.getElementById('rejectCustomSmsMessage');
                                    
                                    const originalText = btn.innerHTML;
                                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
                                    btn.disabled = true;

                                    try {
                                        const response = await fetch('generate_ai_sms.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                            },
                                            body: JSON.stringify({
                                                application_id: <?php echo $application_id; ?>,
                                                jobseeker_name: <?php echo json_encode($jobseeker_name); ?>,
                                                job_title: <?php echo json_encode($job_title); ?>,
                                                company_name: <?php echo json_encode($company_name); ?>,
                                                status: 'rejected'
                                            })
                                        });

                                        const data = await response.json();
                                        
                                        if (data.success) {
                                            textarea.value = data.message;
                                        } else {
                                            alert('Failed to generate AI message: ' + (data.error || 'Unknown error'));
                                        }
                                    } catch (error) {
                                        console.error('Error generating AI text:', error);
                                        alert('An error occurred while communicating with the AI server.');
                                    } finally {
                                        btn.innerHTML = originalText;
                                        btn.disabled = false;
                                    }
                                });
                            </script>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function generateAiEmail(applicationId, status, textareaId, btn) {
            const textarea = document.getElementById(textareaId);
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
            btn.disabled = true;

            try {
                const response = await fetch('generate_ai_sms.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        application_id: applicationId,
                        status: status,
                        is_email: true // Add a flag for email generation if needed
                    })
                });

                const data = await response.json();
                if (data.success) {
                    textarea.value = data.message;
                } else {
                    alert('Failed to generate AI email: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error generating AI text:', error);
                alert('An error occurred while communicating with the AI server.');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize and show SMS toasts if they exist
            var successToastEl = document.getElementById('smsSuccessToast');
            if (successToastEl) {
                var successToast = new bootstrap.Toast(successToastEl, { autohide: true, delay: 5000 });
                successToast.show();
            }

            var errorToastEl = document.getElementById('smsErrorToast');
            if (errorToastEl) {
                var errorToast = new bootstrap.Toast(errorToastEl, { autohide: true, delay: 7000 });
                errorToast.show();
            }

            var emailSuccessToastEl = document.getElementById('emailSuccessToast');
            if (emailSuccessToastEl) {
                var emailSuccessToast = new bootstrap.Toast(emailSuccessToastEl, { autohide: true, delay: 5000 });
                emailSuccessToast.show();
            }

            var emailErrorToastEl = document.getElementById('emailErrorToast');
            if (emailErrorToastEl) {
                var emailErrorToast = new bootstrap.Toast(emailErrorToastEl, { autohide: true, delay: 10000 });
                emailErrorToast.show();
            }
        });
    </script>
</body>
</html>
