<?php
include '../config.php';
requireRole('employee');

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.* FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

// Get active tab from URL
$active_tab = $_GET['tab'] ?? 'schedule';

// Get all interviews with job and company details
$stmt = $pdo->prepare("SELECT ja.*, ja.interview_status, ja.interview_date, ja.interview_time, ja.interview_mode, 
                              ja.interview_location, ja.interview_result, ja.interview_rating, ja.interview_feedback,
                              jp.title, jp.location, jp.salary_range, c.company_name, c.company_logo
                       FROM job_applications ja
                       JOIN job_postings jp ON ja.job_id = jp.id
                       JOIN companies c ON jp.company_id = c.id
                       WHERE ja.employee_id = ? 
                       AND ja.interview_date IS NOT NULL
                       ORDER BY ja.interview_date DESC, ja.interview_time DESC");
$stmt->execute([$profile['id']]);
$all_interviews = $stmt->fetchAll();

// Separate interviews into schedule (upcoming) and results (past)
$upcoming_interviews = [];
$past_interviews = [];
$today = date('Y-m-d');

foreach ($all_interviews as $interview) {
    $interview_datetime = $interview['interview_date'];
    if ($interview_datetime < $today || 
        ($interview_datetime == $today && $interview['interview_time'] && 
         strtotime($interview['interview_time']) < time())) {
        $past_interviews[] = $interview;
    } else {
        $upcoming_interviews[] = $interview;
    }
}

// Sort upcoming by date (soonest first)
usort($upcoming_interviews, function($a, $b) {
    $dateA = $a['interview_date'] . ' ' . ($a['interview_time'] ?? '00:00:00');
    $dateB = $b['interview_date'] . ' ' . ($b['interview_time'] ?? '00:00:00');
    return strtotime($dateA) - strtotime($dateB);
});

// Get interview statistics
$stats = [];
$stats['total'] = count($all_interviews);
$stats['upcoming'] = count($upcoming_interviews);
$stats['completed'] = count($past_interviews);
$stats['passed'] = count(array_filter($all_interviews, function($int) { 
    return $int['interview_result'] === 'passed'; 
}));
$stats['failed'] = count(array_filter($all_interviews, function($int) { 
    return $int['interview_result'] === 'failed'; 
}));
$stats['pending_result'] = count(array_filter($all_interviews, function($int) { 
    return $int['interview_result'] === 'pending' || $int['interview_result'] === null; 
}));

// Get interviews for active tab
$interviews = $active_tab === 'results' ? $past_interviews : $upcoming_interviews;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Interviews - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/minimal.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employee-main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1>My Interviews</h1>
                <p>Track your interview schedule and results</p>
            </div>
            <a href="applications.php" class="btn btn-primary">
                <i class="fas fa-file-alt me-1"></i>View Applications
            </a>
        </div>

        <!-- Interview Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-sm-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $stats['total']; ?></h3>
                        <p class="card-text">Total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $stats['upcoming']; ?></h3>
                        <p class="card-text">Upcoming</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $stats['completed']; ?></h3>
                        <p class="card-text">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $stats['passed']; ?></h3>
                        <p class="card-text">Passed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $stats['failed']; ?></h3>
                        <p class="card-text">Failed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $stats['pending_result']; ?></h3>
                        <p class="card-text">Pending</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="row mb-3">
            <div class="col-12">
                <ul class="nav nav-tabs" id="interviewsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'schedule' ? 'active' : ''; ?>" href="?tab=schedule">
                            <i class="fas fa-calendar-alt me-2"></i>Schedule
                            <?php if ($stats['upcoming'] > 0): ?>
                                <span class="badge bg-info ms-1"><?php echo $stats['upcoming']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'results' ? 'active' : ''; ?>" href="?tab=results">
                            <i class="fas fa-clipboard-check me-2"></i>Results
                            <?php if ($stats['completed'] > 0): ?>
                                <span class="badge bg-success ms-1"><?php echo $stats['completed']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Interviews List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="mb-0">
                            <?php if ($active_tab === 'schedule'): ?>
                                <i class="fas fa-calendar-alt me-2"></i>Upcoming Interviews
                            <?php else: ?>
                                <i class="fas fa-clipboard-check me-2"></i>Interview Results
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($interviews)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-<?php echo $active_tab === 'schedule' ? 'calendar-times' : 'clipboard-list'; ?> fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">
                                    <?php if ($active_tab === 'schedule'): ?>
                                        No Upcoming Interviews
                                    <?php else: ?>
                                        No Interview Results Yet
                                    <?php endif; ?>
                                </h5>
                                <p class="text-muted">
                                    <?php if ($active_tab === 'schedule'): ?>
                                        You don't have any scheduled interviews at the moment.
                                    <?php else: ?>
                                        Your completed interview results will appear here.
                                    <?php endif; ?>
                                </p>
                                <?php if ($active_tab === 'schedule'): ?>
                                    <a href="applications.php?tab=interviews" class="btn btn-primary">
                                        <i class="fas fa-envelope-open-text me-1"></i>Go to Interview Invites
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($interviews as $interview): ?>
                                    <?php
                                    $interview_date = $interview['interview_date'];
                                    $interview_time = $interview['interview_time'] ?? null;
                                    $is_today = $interview_date === $today;
                                    $is_past = $interview_date < $today || ($is_today && $interview_time && strtotime($interview_time) < time());
                                    
                                    // Determine card color based on status
                                    $card_class = '';
                                    $badge_class = '';
                                    $icon_class = '';
                                    
                                    if ($active_tab === 'schedule') {
                                        if ($is_today) {
                                            $card_class = 'border-warning border-2';
                                            $badge_class = 'bg-warning text-dark';
                                            $icon_class = 'text-warning';
                                        } else {
                                            $card_class = 'border-primary border-2';
                                            $badge_class = 'bg-primary text-white';
                                            $icon_class = 'text-primary';
                                        }
                                    } else {
                                        if ($interview['interview_result'] === 'passed') {
                                            $card_class = 'border-success';
                                            $badge_class = 'bg-success text-white';
                                            $icon_class = 'text-success';
                                        } elseif ($interview['interview_result'] === 'failed') {
                                            $card_class = 'border-danger';
                                            $badge_class = 'bg-danger text-white';
                                            $icon_class = 'text-danger';
                                        } else {
                                            $card_class = 'border-warning';
                                            $badge_class = 'bg-warning text-dark';
                                            $icon_class = 'text-warning';
                                        }
                                    }
                                    ?>
                                    <div class="col-md-6">
                                        <div class="card h-100 shadow-sm <?php echo $card_class; ?>" style="transition: transform 0.2s ease, box-shadow 0.2s ease;">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($interview['company_logo']): ?>
                                                            <img src="../<?php echo htmlspecialchars($interview['company_logo']); ?>" 
                                                                 alt="Company Logo" class="me-3" 
                                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0;">
                                                        <?php else: ?>
                                                            <div class="me-3 d-flex align-items-center justify-content-center bg-light rounded" 
                                                                 style="width: 50px; height: 50px;">
                                                                <i class="fas fa-building text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($interview['title']); ?></h6>
                                                            <p class="text-muted mb-0 small"><?php echo htmlspecialchars($interview['company_name']); ?></p>
                                                        </div>
                                                    </div>
                                                    <?php if ($active_tab === 'schedule'): ?>
                                                        <?php if ($is_today): ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <i class="fas fa-clock me-1"></i>Today
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <i class="fas fa-calendar me-1"></i>Scheduled
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php 
                                                            if ($interview['interview_result'] === 'passed') {
                                                                echo '<i class="fas fa-check-circle me-1"></i>Passed';
                                                            } elseif ($interview['interview_result'] === 'failed') {
                                                                echo '<i class="fas fa-times-circle me-1"></i>Failed';
                                                            } else {
                                                                echo '<i class="fas fa-hourglass-half me-1"></i>Pending';
                                                            }
                                                            ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <hr class="my-3">

                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-day <?php echo $icon_class; ?> me-2"></i>
                                                            <div>
                                                                <small class="text-muted d-block">Date</small>
                                                                <strong><?php echo date('F j, Y', strtotime($interview_date)); ?></strong>
                                                                <?php if ($is_today && $active_tab === 'schedule'): ?>
                                                                    <span class="badge bg-warning text-dark ms-2">Today</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($interview_time): ?>
                                                        <div class="col-12">
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-clock <?php echo $icon_class; ?> me-2"></i>
                                                                <div>
                                                                    <small class="text-muted d-block">Time</small>
                                                                    <strong><?php echo date('g:i A', strtotime($interview_time)); ?></strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($interview['interview_mode']): ?>
                                                        <div class="col-12">
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-<?php echo $interview['interview_mode'] === 'online' ? 'video' : ($interview['interview_mode'] === 'phone' ? 'phone' : 'map-marker-alt'); ?> <?php echo $icon_class; ?> me-2"></i>
                                                                <div>
                                                                    <small class="text-muted d-block">Mode</small>
                                                                    <strong><?php echo ucfirst($interview['interview_mode']); ?></strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($interview['interview_location']): ?>
                                                        <div class="col-12">
                                                            <div class="d-flex align-items-start">
                                                                <i class="fas fa-map-marker-alt <?php echo $icon_class; ?> me-2 mt-1"></i>
                                                                <div>
                                                                    <small class="text-muted d-block">Location</small>
                                                                    <strong><?php echo htmlspecialchars($interview['interview_location']); ?></strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($active_tab === 'results' && $interview['interview_result']): ?>
                                                        <div class="col-12">
                                                            <div class="d-flex align-items-start">
                                                                <i class="fas fa-<?php echo $interview['interview_result'] === 'passed' ? 'check-circle text-success' : ($interview['interview_result'] === 'failed' ? 'times-circle text-danger' : 'hourglass-half text-warning'); ?> me-2 mt-1"></i>
                                                                <div>
                                                                    <small class="text-muted d-block">Result</small>
                                                                    <strong><?php echo ucfirst($interview['interview_result']); ?></strong>
                                                                    <?php if ($interview['interview_rating']): ?>
                                                                        <div class="mt-1">
                                                                            <?php 
                                                                            $rating = (int)$interview['interview_rating'];
                                                                            for ($i = 1; $i <= 5; $i++): 
                                                                            ?>
                                                                                <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-muted'; ?>"></i>
                                                                            <?php endfor; ?>
                                                                            <small class="text-muted ms-1">(<?php echo $rating; ?>/5)</small>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($active_tab === 'results' && $interview['interview_feedback']): ?>
                                                        <div class="col-12">
                                                            <div class="bg-light p-3 rounded">
                                                                <small class="text-muted d-block mb-1">Feedback</small>
                                                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($interview['interview_feedback'])); ?></p>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <hr class="my-3">

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="../job-details.php?id=<?php echo $interview['job_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>View Job
                                                    </a>
                                                    <a href="applications.php?tab=interviews" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-file-alt me-1"></i>View Application
                                                    </a>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .interview-card { border-left: 3px solid #e2e8f0; }
        .interview-card.today { border-left-color: #f59e0b; }
        .interview-card.scheduled { border-left-color: #3b82f6; }
        .interview-card.passed { border-left-color: #10b981; }
        .interview-card.failed { border-left-color: #ef4444; }
    </style>
</body>
</html>
