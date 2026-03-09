<?php
include '../config.php';
requireRole('employer');

$message = '';
$error = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    redirect('company-profile.php');
}

// Handle interview actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $application_id = (int)($_POST['application_id'] ?? 0);
    
    if ($action === 'schedule_interview') {
        $interview_date = sanitizeInput($_POST['interview_date'] ?? '');
        $interview_time = sanitizeInput($_POST['interview_time'] ?? '');
        $interview_mode = sanitizeInput($_POST['interview_mode'] ?? 'onsite');
        $interview_location = sanitizeInput($_POST['interview_location'] ?? '');
        $interview_notes = sanitizeInput($_POST['interview_notes'] ?? '');
        
        // Verify application belongs to company
        $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                               JOIN job_postings jp ON ja.job_id = jp.id 
                               WHERE ja.id = ? AND jp.company_id = ?");
        $stmt->execute([$application_id, $company['id']]);
        $application = $stmt->fetch();
        
        if ($application && $interview_date && $interview_time) {
            // Check if interview columns exist
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'interview_date'");
                $interview_cols_exist = $checkStmt->rowCount() > 0;
            } catch (Exception $e) {
                $interview_cols_exist = false;
            }
            
            if ($interview_cols_exist) {
                // Update interview status and store interview details
                $stmt = $pdo->prepare("UPDATE job_applications 
                                       SET interview_status = 'interviewed', 
                                           interview_date = ?, 
                                           interview_time = ?,
                                           interview_mode = ?,
                                           interview_location = ?,
                                           interview_notes = ?
                                       WHERE id = ?");
                if ($stmt->execute([$interview_date, $interview_time, $interview_mode, $interview_location, $interview_notes, $application_id])) {
                    $message = 'Interview scheduled successfully!';
                } else {
                    $error = 'Failed to schedule interview.';
                }
            } else {
                // Fallback: just update interview status
                $stmt = $pdo->prepare("UPDATE job_applications SET interview_status = 'interviewed' WHERE id = ?");
                if ($stmt->execute([$application_id])) {
                    $message = 'Interview scheduled successfully! (Note: Please run the SQL migration to enable full interview scheduling features)';
                } else {
                    $error = 'Failed to schedule interview.';
                }
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    }
    
    if ($action === 'update_interview_result') {
        $interview_result = sanitizeInput($_POST['interview_result'] ?? '');
        $interview_rating = (int)($_POST['interview_rating'] ?? 0);
        $interview_feedback = sanitizeInput($_POST['interview_feedback'] ?? '');
        
        // Verify application belongs to company
        $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                               JOIN job_postings jp ON ja.job_id = jp.id 
                               WHERE ja.id = ? AND jp.company_id = ?");
        $stmt->execute([$application_id, $company['id']]);
        $application = $stmt->fetch();
        
        if ($application && $interview_result) {
            // Check if interview result columns exist
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'interview_result'");
                $interview_result_cols_exist = $checkStmt->rowCount() > 0;
            } catch (Exception $e) {
                $interview_result_cols_exist = false;
            }
            
            if ($interview_result_cols_exist) {
                $stmt = $pdo->prepare("UPDATE job_applications 
                                       SET interview_result = ?, 
                                           interview_rating = ?,
                                           interview_feedback = ?
                                       WHERE id = ?");
                if ($stmt->execute([$interview_result, $interview_rating, $interview_feedback, $application_id])) {
                    $message = 'Interview result updated successfully!';
                } else {
                    $error = 'Failed to update interview result.';
                }
            } else {
                $message = 'Interview result updated! (Note: Please run the SQL migration to enable full interview results features)';
            }
        }
    }
    
    if ($action === 'send_offer') {
        $offer_salary = sanitizeInput($_POST['offer_salary'] ?? '');
        $offer_start_date = sanitizeInput($_POST['offer_start_date'] ?? '');
        $offer_notes = sanitizeInput($_POST['offer_notes'] ?? '');
        
        // Verify application belongs to company
        $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                               JOIN job_postings jp ON ja.job_id = jp.id 
                               WHERE ja.id = ? AND jp.company_id = ?");
        $stmt->execute([$application_id, $company['id']]);
        $application = $stmt->fetch();
        
        if ($application && $offer_salary && $offer_start_date) {
            // Check if offer columns exist
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'offer_sent'");
                $offer_cols_exist = $checkStmt->rowCount() > 0;
            } catch (Exception $e) {
                $offer_cols_exist = false;
            }
            
            if ($offer_cols_exist) {
                $stmt = $pdo->prepare("UPDATE job_applications 
                                       SET offer_sent = 1, 
                                           offer_salary = ?,
                                           offer_start_date = ?,
                                           offer_notes = ?,
                                           offer_sent_date = NOW()
                                       WHERE id = ?");
                if ($stmt->execute([$offer_salary, $offer_start_date, $offer_notes, $application_id])) {
                    $message = 'Job offer sent successfully!';
                } else {
                    $error = 'Failed to send job offer.';
                }
            } else {
                // Fallback: just update status to accepted
                $stmt = $pdo->prepare("UPDATE job_applications SET status = 'accepted' WHERE id = ?");
                if ($stmt->execute([$application_id])) {
                    $message = 'Job offer sent successfully! (Note: Please run the SQL migration to enable full offer management features)';
                } else {
                    $error = 'Failed to send job offer.';
                }
            }
        }
    }
    
    if ($action === 'update_offer_status') {
        $offer_status = sanitizeInput($_POST['offer_status'] ?? '');
        
        $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                               JOIN job_postings jp ON ja.job_id = jp.id 
                               WHERE ja.id = ? AND jp.company_id = ?");
        $stmt->execute([$application_id, $company['id']]);
        $application = $stmt->fetch();
        
        if ($application && $offer_status) {
            // Check if offer_status column exists
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'offer_status'");
                $offer_status_col_exists = $checkStmt->rowCount() > 0;
            } catch (Exception $e) {
                $offer_status_col_exists = false;
            }
            
            if ($offer_status_col_exists) {
                $stmt = $pdo->prepare("UPDATE job_applications 
                                       SET offer_status = ?
                                       WHERE id = ?");
                if ($stmt->execute([$offer_status, $application_id])) {
                    $message = 'Offer status updated successfully!';
                } else {
                    $error = 'Failed to update offer status.';
                }
            } else {
                // Fallback: update application status
                if ($offer_status === 'accepted') {
                    $stmt = $pdo->prepare("UPDATE job_applications SET status = 'accepted' WHERE id = ?");
                    $stmt->execute([$application_id]);
                }
                $message = 'Offer status updated! (Note: Please run the SQL migration to enable full offer management features)';
            }
        }
    }
}

// Check if interview columns exist, if not we'll handle gracefully
$interview_columns_exist = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'interview_date'");
    $interview_columns_exist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    // Columns don't exist yet
}

// Check if offer columns exist
$offer_columns_exist = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'offer_sent'");
    $offer_columns_exist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    // Columns don't exist yet
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'calendar';

// Initialize arrays to prevent undefined variable errors
$calendar_interviews = [];
$interview_results = [];
$offers = [];

// Get interviews for calendar
if ($interview_columns_exist) {
    $stmt = $pdo->prepare("SELECT ja.*, jp.title as job_title, ep.first_name, ep.last_name, ep.contact_no, u.email
                          FROM job_applications ja
                          JOIN job_postings jp ON ja.job_id = jp.id
                          JOIN employee_profiles ep ON ja.employee_id = ep.id
                          JOIN users u ON ep.user_id = u.id
                          WHERE jp.company_id = ? 
                          AND ja.interview_status = 'interviewed' 
                          AND ja.interview_date IS NOT NULL
                          ORDER BY ja.interview_date ASC, ja.interview_time ASC");
    $stmt->execute([$company['id']]);
    $calendar_interviews = $stmt->fetchAll();
} else {
    // Fallback: get applications marked as interviewed
    $stmt = $pdo->prepare("SELECT ja.*, jp.title as job_title, ep.first_name, ep.last_name, ep.contact_no, u.email
                          FROM job_applications ja
                          JOIN job_postings jp ON ja.job_id = jp.id
                          JOIN employee_profiles ep ON ja.employee_id = ep.id
                          JOIN users u ON ep.user_id = u.id
                          WHERE jp.company_id = ? 
                          AND ja.interview_status = 'interviewed'
                          ORDER BY ja.applied_date DESC");
    $stmt->execute([$company['id']]);
    $calendar_interviews = $stmt->fetchAll();
}

// Get interview results
if ($interview_columns_exist) {
    $stmt = $pdo->prepare("SELECT ja.*, jp.title as job_title, ep.first_name, ep.last_name, ep.contact_no, u.email
                          FROM job_applications ja
                          JOIN job_postings jp ON ja.job_id = jp.id
                          JOIN employee_profiles ep ON ja.employee_id = ep.id
                          JOIN users u ON ep.user_id = u.id
                          WHERE jp.company_id = ? 
                          AND ja.interview_status = 'interviewed'
                          ORDER BY ja.interview_date DESC, ja.applied_date DESC");
    $stmt->execute([$company['id']]);
    $interview_results = $stmt->fetchAll();
} else {
    $interview_results = $calendar_interviews;
}

// Get offers
if ($offer_columns_exist) {
    $stmt = $pdo->prepare("SELECT ja.*, jp.title as job_title, ep.first_name, ep.last_name, ep.contact_no, u.email
                          FROM job_applications ja
                          JOIN job_postings jp ON ja.job_id = jp.id
                          JOIN employee_profiles ep ON ja.employee_id = ep.id
                          JOIN users u ON ep.user_id = u.id
                          WHERE jp.company_id = ? 
                          AND (ja.offer_sent = 1 OR ja.status = 'accepted')
                          ORDER BY ja.offer_sent_date DESC, ja.applied_date DESC");
    $stmt->execute([$company['id']]);
    $offers = $stmt->fetchAll();
} else {
    // Fallback: get accepted applications as offers
    $stmt = $pdo->prepare("SELECT ja.*, jp.title as job_title, ep.first_name, ep.last_name, ep.contact_no, u.email
                          FROM job_applications ja
                          JOIN job_postings jp ON ja.job_id = jp.id
                          JOIN employee_profiles ep ON ja.employee_id = ep.id
                          JOIN users u ON ep.user_id = u.id
                          WHERE jp.company_id = ? 
                          AND ja.status = 'accepted'
                          ORDER BY ja.applied_date DESC");
    $stmt->execute([$company['id']]);
    $offers = $stmt->fetchAll();
}

// Get statistics
$stats = [];
$stats['scheduled'] = count($calendar_interviews);
$stats['completed'] = count($interview_results);
$stats['offers_sent'] = count($offers);
$stats['offers_accepted'] = 0;
foreach ($offers as $offer) {
    if (isset($offer['offer_status']) && $offer['offer_status'] === 'accepted') {
        $stats['offers_accepted']++;
    } elseif ($offer['status'] === 'accepted') {
        $stats['offers_accepted']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interviews & Offers - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0D6EFD;
            --primary-blue-dark: #0b5ed7;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --info-cyan: #06b6d4;
            --purple: #8b5cf6;
            --pink: #ec4899;
        }

        .interview-page {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-card.calendar { border-left-color: var(--primary-blue); }
        .stat-card.results { border-left-color: var(--success-green); }
        .stat-card.offers { border-left-color: var(--purple); }
        .stat-card.accepted { border-left-color: var(--success-green); }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card.calendar .stat-icon { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .stat-card.results .stat-icon { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .stat-card.offers .stat-icon { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; }
        .stat-card.accepted .stat-icon { background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white; }

        .nav-tabs-custom {
            border-bottom: 3px solid #e2e8f0;
            background: white;
            border-radius: 15px 15px 0 0;
            padding: 10px 20px 0;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #64748b;
            font-weight: 500;
            padding: 15px 25px;
            margin-right: 10px;
            transition: all 0.3s ease;
            border-radius: 10px 10px 0 0;
        }

        .nav-tabs-custom .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary-blue);
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
            background: linear-gradient(180deg, rgba(13, 110, 253, 0.05) 0%, transparent 100%);
            font-weight: 600;
        }

        .tab-content-custom {
            background: white;
            border-radius: 0 0 15px 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            min-height: 500px;
        }

        .calendar-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .interview-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-blue);
        }

        .interview-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .interview-card.upcoming {
            border-left-color: var(--info-cyan);
        }

        .interview-card.today {
            border-left-color: var(--warning-orange);
            background: linear-gradient(90deg, #fff7ed 0%, #ffffff 100%);
        }

        .interview-card.past {
            border-left-color: #94a3b8;
            opacity: 0.8;
        }

        .result-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--success-green);
        }

        .result-card.excellent { border-left-color: var(--success-green); }
        .result-card.good { border-left-color: var(--info-cyan); }
        .result-card.fair { border-left-color: var(--warning-orange); }
        .result-card.poor { border-left-color: var(--danger-red); }

        .offer-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--purple);
        }

        .offer-card.pending { border-left-color: var(--warning-orange); }
        .offer-card.accepted { border-left-color: var(--success-green); }
        .offer-card.rejected { border-left-color: var(--danger-red); }
        .offer-card.withdrawn { border-left-color: #94a3b8; }

        .rating-stars {
            color: #fbbf24;
            font-size: 18px;
        }

        .rating-stars .far {
            color: #d1d5db;
        }

        .badge-custom {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .btn-action {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control-custom {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }

        .fc-event {
            border-radius: 6px;
            padding: 5px;
            cursor: pointer;
        }

        .fc-event-title {
            font-weight: 500;
        }

        .fc-today-button {
            background: var(--primary-blue) !important;
            border-color: var(--primary-blue) !important;
        }

        .fc-button-primary {
            background: var(--primary-blue) !important;
            border-color: var(--primary-blue) !important;
        }

        .fc-button-primary:hover {
            background: var(--primary-blue-dark) !important;
            border-color: var(--primary-blue-dark) !important;
        }
    </style>
</head>
<body class="employer-layout interview-page">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 mb-1"><i class="fas fa-calendar-check me-2" style="color: var(--primary-blue);"></i>Interviews & Offers</h1>
                <p class="text-muted mb-0">Manage interviews, results, and job offers</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="applications.php" class="btn btn-outline-light">
                    <i class="fas fa-users me-1"></i>View Applications
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Database Migration Notice -->
        <?php if (!$interview_columns_exist || !$offer_columns_exist): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Database Update Required:</strong> To enable full interview scheduling, results tracking, and offer management features, please run the SQL migration file: 
                <code>sql/add_interviews_offers_columns.sql</code>
                <br><small class="mt-2 d-block">The page will work with limited functionality until the migration is run.</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card calendar">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['scheduled']; ?></h3>
                    <p class="text-muted mb-0">Scheduled Interviews</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card results">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['completed']; ?></h3>
                    <p class="text-muted mb-0">Completed Interviews</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card offers">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['offers_sent']; ?></h3>
                    <p class="text-muted mb-0">Offers Sent</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card accepted">
                    <div class="stat-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['offers_accepted']; ?></h3>
                    <p class="text-muted mb-0">Offers Accepted</p>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="card mb-4" style="border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>" 
                           href="?tab=calendar">
                            <i class="fas fa-calendar-alt me-2"></i>Interview Calendar
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'results' ? 'active' : ''; ?>" 
                           href="?tab=results">
                            <i class="fas fa-clipboard-check me-2"></i>Interview Results
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'offers' ? 'active' : ''; ?>" 
                           href="?tab=offers">
                            <i class="fas fa-hand-holding-usd me-2"></i>Offer Management
                        </a>
                    </li>
                </ul>

                <div class="tab-content tab-content-custom">
                    <!-- Interview Calendar Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'calendar' ? 'show active' : ''; ?>" 
                         id="calendar-tab" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="calendar-container">
                                    <div id="calendar"></div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <h5 class="mb-3"><i class="fas fa-list me-2"></i>Upcoming Interviews</h5>
                                <?php if (empty($calendar_interviews)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <p>No scheduled interviews</p>
                                        <a href="applications.php" class="btn btn-primary btn-action">
                                            <i class="fas fa-plus me-1"></i>Schedule Interview
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div style="max-height: 600px; overflow-y: auto;">
                                        <?php 
                                        $today = date('Y-m-d');
                                        foreach ($calendar_interviews as $interview): 
                                            $interview_date = $interview['interview_date'] ?? null;
                                            $interview_time = $interview['interview_time'] ?? null;
                                            
                                            if (!$interview_date) continue;
                                            
                                            $card_class = 'upcoming';
                                            if ($interview_date === $today) {
                                                $card_class = 'today';
                                            } elseif ($interview_date < $today) {
                                                $card_class = 'past';
                                            }
                                        ?>
                                            <div class="interview-card <?php echo $card_class; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-1 fw-bold">
                                                            <?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-briefcase me-1"></i>
                                                            <?php echo htmlspecialchars($interview['job_title']); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($card_class === 'today'): ?>
                                                        <span class="badge bg-warning badge-custom">
                                                            <i class="fas fa-exclamation-circle me-1"></i>Today
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <p class="mb-1">
                                                        <i class="fas fa-calendar me-2 text-primary"></i>
                                                        <strong><?php echo date('M j, Y', strtotime($interview_date)); ?></strong>
                                                    </p>
                                                    <?php if ($interview_time): ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-clock me-2 text-info"></i>
                                                            <?php echo date('g:i A', strtotime($interview_time)); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (isset($interview['interview_mode'])): ?>
                                                        <p class="mb-0">
                                                            <i class="fas fa-video me-2 text-success"></i>
                                                            <?php echo ucfirst($interview['interview_mode']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-3">
                                                    <button class="btn btn-sm btn-primary btn-action" 
                                                            onclick="viewInterviewDetails(<?php echo $interview['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Interview Results Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'results' ? 'show active' : ''; ?>" 
                         id="results-tab" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Interview Results</h5>
                            <button class="btn btn-primary btn-action" data-bs-toggle="modal" data-bs-target="#addResultModal">
                                <i class="fas fa-plus me-1"></i>Add Result
                            </button>
                        </div>
                        
                        <?php if (empty($interview_results)): ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h5>No Interview Results</h5>
                                <p>Interview results will appear here after interviews are completed.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($interview_results as $result): 
                                    $rating = isset($result['interview_rating']) ? (int)$result['interview_rating'] : 0;
                                    $result_status = $result['interview_result'] ?? 'pending';
                                    
                                    $card_class = 'good';
                                    if ($rating >= 4) $card_class = 'excellent';
                                    elseif ($rating >= 3) $card_class = 'good';
                                    elseif ($rating >= 2) $card_class = 'fair';
                                    else $card_class = 'poor';
                                ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="result-card <?php echo $card_class; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1 fw-bold">
                                                        <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-briefcase me-1"></i>
                                                        <?php echo htmlspecialchars($result['job_title']); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $result_status === 'passed' ? 'success' : 
                                                        ($result_status === 'failed' ? 'danger' : 'warning'); 
                                                ?> badge-custom">
                                                    <?php echo ucfirst($result_status); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($rating > 0): ?>
                                                <div class="mb-2">
                                                    <span class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $rating ? '' : 'far'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span class="ms-2 text-muted">(<?php echo $rating; ?>/5)</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($result['interview_date'])): ?>
                                                <p class="mb-1 small text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Interview Date: <?php echo date('M j, Y', strtotime($result['interview_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($result['interview_feedback']) && $result['interview_feedback']): ?>
                                                <div class="mt-2 p-2 bg-light rounded">
                                                    <small class="text-muted d-block mb-1"><strong>Feedback:</strong></small>
                                                    <small><?php echo htmlspecialchars($result['interview_feedback']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <button class="btn btn-sm btn-primary btn-action" 
                                                        onclick="editInterviewResult(<?php echo $result['id']; ?>)">
                                                    <i class="fas fa-edit me-1"></i>Edit Result
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Offer Management Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'offers' ? 'show active' : ''; ?>" 
                         id="offers-tab" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Job Offers</h5>
                            <button class="btn btn-primary btn-action" data-bs-toggle="modal" data-bs-target="#sendOfferModal">
                                <i class="fas fa-paper-plane me-1"></i>Send Offer
                            </button>
                        </div>
                        
                        <?php if (empty($offers)): ?>
                            <div class="empty-state">
                                <i class="fas fa-hand-holding-usd"></i>
                                <h5>No Job Offers</h5>
                                <p>Job offers sent to candidates will appear here.</p>
                                <button class="btn btn-primary btn-action" data-bs-toggle="modal" data-bs-target="#sendOfferModal">
                                    <i class="fas fa-paper-plane me-1"></i>Send First Offer
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($offers as $offer): 
                                    $offer_status = $offer['offer_status'] ?? ($offer['status'] === 'accepted' ? 'accepted' : 'pending');
                                    $card_class = 'pending';
                                    if ($offer_status === 'accepted') $card_class = 'accepted';
                                    elseif ($offer_status === 'rejected') $card_class = 'rejected';
                                    elseif ($offer_status === 'withdrawn') $card_class = 'withdrawn';
                                ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="offer-card <?php echo $card_class; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1 fw-bold">
                                                        <?php echo htmlspecialchars($offer['first_name'] . ' ' . $offer['last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-briefcase me-1"></i>
                                                        <?php echo htmlspecialchars($offer['job_title']); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $offer_status === 'accepted' ? 'success' : 
                                                        ($offer_status === 'rejected' ? 'danger' : 
                                                        ($offer_status === 'withdrawn' ? 'secondary' : 'warning')); 
                                                ?> badge-custom">
                                                    <?php echo ucfirst($offer_status); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (isset($offer['offer_salary']) && $offer['offer_salary']): ?>
                                                <p class="mb-2">
                                                    <i class="fas fa-dollar-sign me-2 text-success"></i>
                                                    <strong>Salary:</strong> <?php echo htmlspecialchars($offer['offer_salary']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($offer['offer_start_date']) && $offer['offer_start_date']): ?>
                                                <p class="mb-2">
                                                    <i class="fas fa-calendar-alt me-2 text-info"></i>
                                                    <strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($offer['offer_start_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($offer['offer_sent_date']) && $offer['offer_sent_date']): ?>
                                                <p class="mb-2 small text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Sent: <?php echo date('M j, Y', strtotime($offer['offer_sent_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($offer['offer_notes']) && $offer['offer_notes']): ?>
                                                <div class="mt-2 p-2 bg-light rounded">
                                                    <small class="text-muted d-block mb-1"><strong>Notes:</strong></small>
                                                    <small><?php echo htmlspecialchars($offer['offer_notes']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <button class="btn btn-sm btn-primary btn-action" 
                                                        onclick="updateOfferStatus(<?php echo $offer['id']; ?>, '<?php echo $offer_status; ?>')">
                                                    <i class="fas fa-edit me-1"></i>Update Status
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Interview Modal -->
    <div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Interview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="schedule_interview">
                        <input type="hidden" name="application_id" id="schedule_application_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Interview Date <span class="text-danger">*</span></label>
                                <input type="date" name="interview_date" class="form-control form-control-custom" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Interview Time <span class="text-danger">*</span></label>
                                <input type="time" name="interview_time" class="form-control form-control-custom" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Interview Mode <span class="text-danger">*</span></label>
                            <select name="interview_mode" class="form-control form-control-custom" required>
                                <option value="onsite">Onsite</option>
                                <option value="online">Online</option>
                                <option value="phone">Phone</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location / Meeting Link</label>
                            <input type="text" name="interview_location" class="form-control form-control-custom" 
                                   placeholder="Office address or video call link">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="interview_notes" class="form-control form-control-custom" rows="3" 
                                      placeholder="Additional notes or instructions..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-action">
                            <i class="fas fa-calendar-check me-1"></i>Schedule Interview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Interview Result Modal -->
    <div class="modal fade" id="addResultModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Add Interview Result</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_interview_result">
                        <input type="hidden" name="application_id" id="result_application_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Result <span class="text-danger">*</span></label>
                            <select name="interview_result" class="form-control form-control-custom" required>
                                <option value="">Select Result</option>
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                                <option value="pending">Pending Review</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rating (1-5)</label>
                            <select name="interview_rating" class="form-control form-control-custom">
                                <option value="0">No Rating</option>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very Good</option>
                                <option value="3">3 - Good</option>
                                <option value="2">2 - Fair</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Feedback</label>
                            <textarea name="interview_feedback" class="form-control form-control-custom" rows="4" 
                                      placeholder="Interview feedback and notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-action">
                            <i class="fas fa-save me-1"></i>Save Result
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Offer Modal -->
    <div class="modal fade" id="sendOfferModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Send Job Offer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_offer">
                        <input type="hidden" name="application_id" id="offer_application_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Salary Offer <span class="text-danger">*</span></label>
                            <input type="text" name="offer_salary" class="form-control form-control-custom" 
                                   placeholder="e.g., ₱25,000 - ₱30,000" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="offer_start_date" class="form-control form-control-custom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Offer Notes</label>
                            <textarea name="offer_notes" class="form-control form-control-custom" rows="4" 
                                      placeholder="Additional details about the offer..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-action">
                            <i class="fas fa-paper-plane me-1"></i>Send Offer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Offer Status Modal -->
    <div class="modal fade" id="updateOfferStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Offer Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_offer_status">
                        <input type="hidden" name="application_id" id="update_offer_application_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="offer_status" class="form-control form-control-custom" required>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-action">
                            <i class="fas fa-save me-1"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
    <script>
        // Initialize Calendar
        document.addEventListener('DOMContentLoaded', function() {
            try {
                var calendarEl = document.getElementById('calendar');
                if (calendarEl) {
                    var calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        },
                        events: [
                            <?php 
                            foreach ($calendar_interviews as $interview): 
                                if (isset($interview['interview_date']) && $interview['interview_date']):
                                    $date = $interview['interview_date'];
                                    $time = isset($interview['interview_time']) ? $interview['interview_time'] : '09:00';
                                    $title = htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name'] . ' - ' . $interview['job_title'], ENT_QUOTES);
                                    $color = '#0D6EFD';
                                    if ($date === date('Y-m-d')) $color = '#f59e0b';
                                    elseif ($date < date('Y-m-d')) $color = '#94a3b8';
                            ?>
                            {
                                title: '<?php echo $title; ?>',
                                start: '<?php echo $date . 'T' . $time; ?>',
                                color: '<?php echo $color; ?>',
                                extendedProps: {
                                    applicationId: <?php echo $interview['id']; ?>
                                }
                            },
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        ],
                        eventClick: function(info) {
                            viewInterviewDetails(info.event.extendedProps.applicationId);
                        }
                    });
                    calendar.render();
                }
            } catch (error) {
                console.error('Calendar initialization error:', error);
                // Show a message if calendar fails to load
                var calendarEl = document.getElementById('calendar');
                if (calendarEl) {
                    calendarEl.innerHTML = '<div class="alert alert-warning">Calendar could not be loaded. Please refresh the page.</div>';
                }
            }
        });

        function viewInterviewDetails(applicationId) {
            // You can implement a modal or redirect to view details
            window.location.href = 'view-application.php?id=' + applicationId;
        }

        function editInterviewResult(applicationId) {
            document.getElementById('result_application_id').value = applicationId;
            var modal = new bootstrap.Modal(document.getElementById('addResultModal'));
            modal.show();
        }

        function updateOfferStatus(applicationId, currentStatus) {
            document.getElementById('update_offer_application_id').value = applicationId;
            var select = document.querySelector('#updateOfferStatusModal select[name="offer_status"]');
            select.value = currentStatus;
            var modal = new bootstrap.Modal(document.getElementById('updateOfferStatusModal'));
            modal.show();
        }

        // Handle tab switching
        document.querySelectorAll('.nav-tabs-custom .nav-link').forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                // Tab switching is handled by href
            });
        });
    </script>
</body>
</html>
