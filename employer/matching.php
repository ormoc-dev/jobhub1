<?php
include '../config.php';
requireRole('employer');
include 'includes/matching.php';

$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    redirect('company-profile.php');
}

$jobsStmt = $pdo->prepare("SELECT jp.*, jc.category_name 
                           FROM job_postings jp 
                           LEFT JOIN job_categories jc ON jp.category_id = jc.id
                           WHERE jp.company_id = ? AND jp.status = 'active'
                           ORDER BY jp.posted_date DESC");
$jobsStmt->execute([$company['id']]);
$jobs = $jobsStmt->fetchAll();

$selectedJobId = $_GET['job_id'] ?? ($jobs[0]['id'] ?? null);
$minScore = 100;
$bestMinScore = 100;
$activeTab = $_GET['tab'] ?? 'recommended';
if (!in_array($activeTab, ['recommended', 'bestfit', 'aimatch'], true)) {
    $activeTab = 'recommended';
}

$selectedJob = null;
foreach ($jobs as $job) {
    if ((string) $job['id'] === (string) $selectedJobId) {
        $selectedJob = $job;
        break;
    }
}

$allApplicants = [];
$jobApplicants = [];
if (!empty($jobs)) {
    // Get all active employee profiles (not just applicants)
    $allApplicantsStmt = $pdo->prepare("SELECT DISTINCT u.id as user_id, u.email, u.profile_picture, u.status as user_status,
                                               ep.*, ep.id as employee_profile_id
                                        FROM employee_profiles ep
                                        JOIN users u ON ep.user_id = u.id
                                        WHERE u.role = 'employee' AND u.status = 'active'");
    $allApplicantsStmt->execute();
    $allApplicants = $allApplicantsStmt->fetchAll();

    // For selected job, use all candidates (not filtered by applications)
    $jobApplicants = $allApplicants;
}

$recommendedCandidates = [];
if ($selectedJob && !empty($jobApplicants)) {
    foreach ($jobApplicants as $candidate) {
        $score = computeStrictSkillExperienceMatchScore($selectedJob, $candidate);
        // Show only 100% matches (exact skills and experience match)
        if ($score === 100) {
            $candidate['match_score'] = $score;
            $recommendedCandidates[] = $candidate;
        }
    }

    usort($recommendedCandidates, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
}

$aiCandidates = [];
if ($selectedJob && !empty($jobApplicants)) {
    foreach ($jobApplicants as $candidate) {
        // Use strict matching function - skills and experience only, 100% exact match
        $score = computeStrictSkillExperienceMatchScore($selectedJob, $candidate);
        // Show only 100% matches (exact skills and experience match)
        if ($score === 100) {
            $candidate['ai_score'] = $score;
            $aiCandidates[] = $candidate;
        }
    }

    usort($aiCandidates, function ($a, $b) {
        return $b['ai_score'] <=> $a['ai_score'];
    });
}

$bestFits = [];
if (!empty($jobs) && !empty($allApplicants)) {
    foreach ($allApplicants as $candidate) {
        $bestScore = 0;
        $bestJob = null;

        foreach ($jobs as $job) {
            $score = computeStrictSkillExperienceMatchScore($job, $candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestJob = $job;
            }
        }

        // Show only 100% matches (exact skills and experience match)
        if ($bestJob && $bestScore === 100) {
            $candidate['best_score'] = $bestScore;
            $candidate['best_job_title'] = $bestJob['title'];
            $candidate['best_job_id'] = $bestJob['id'];
            $bestFits[] = $candidate;
        }
    }

    usort($bestFits, function ($a, $b) {
        return $b['best_score'] <=> $a['best_score'];
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matching - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-info: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.16);
        }
        
        .matching-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .matching-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }
        
        .nav-pills .nav-link {
            border-radius: 12px;
            padding: 12px 24px;
            margin-right: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .nav-pills .nav-link:not(.active) {
            background: #f8f9fa;
            color: #6b7280;
        }
        
        .nav-pills .nav-link.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .nav-pills .nav-link:hover:not(.active) {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: none;
        }
        
        .results-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            border: none;
            overflow: hidden;
        }
        
        .results-card .card-header {
            background: var(--gradient-info);
            color: white;
            padding: 1.25rem 1.5rem;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .score-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .score-badge.excellent {
            background: var(--gradient-success);
            color: white;
        }
        
        .score-badge.good {
            background: var(--gradient-info);
            color: white;
        }
        
        .score-badge.fair {
            background: var(--gradient-warning);
            color: white;
        }
        
        .candidate-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .candidate-card:hover {
            background: #f8f9ff;
            border-left-color: #667eea;
            transform: translateX(5px);
        }
        
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            color: #4b5563;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .table tbody td {
            vertical-align: middle;
            padding: 1rem;
        }
        
        .candidate-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .candidate-avatar:hover {
            border-color: #667eea;
            transform: scale(1.1);
        }
        
        .btn-action {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .stats-badge {
            display: inline-block;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="matching-header">
            <h1><i class="fas fa-users-cog me-2"></i>Smart Candidate Matching</h1>
            <p class="mb-0 mt-2 opacity-90">Discover the perfect candidates for your job openings</p>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="alert alert-warning" style="border-radius: 12px; border: none; box-shadow: var(--shadow-sm);">
                <i class="fas fa-exclamation-circle me-2"></i>
                You don't have any active job postings yet.
                <a href="post-job.php" class="alert-link fw-bold">Post a job</a> to see matching results.
            </div>
        <?php else: ?>
            <ul class="nav nav-pills mb-4" id="matchingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'recommended' ? 'active' : ''; ?>" id="recommended-tab" data-bs-toggle="pill" data-bs-target="#recommended" type="button" role="tab" aria-controls="recommended" aria-selected="<?php echo $activeTab === 'recommended' ? 'true' : 'false'; ?>">
                        <i class="fas fa-user-check me-2"></i> Top Matched Applicants
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'bestfit' ? 'active' : ''; ?>" id="bestfit-tab" data-bs-toggle="pill" data-bs-target="#bestfit" type="button" role="tab" aria-controls="bestfit" aria-selected="<?php echo $activeTab === 'bestfit' ? 'true' : 'false'; ?>">
                        <i class="fas fa-star me-2"></i> Perfect Fit Candidates
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'aimatch' ? 'active' : ''; ?>" id="aimatch-tab" data-bs-toggle="pill" data-bs-target="#aimatch" type="button" role="tab" aria-controls="aimatch" aria-selected="<?php echo $activeTab === 'aimatch' ? 'true' : 'false'; ?>">
                        <i class="fas fa-brain me-2"></i> AI-Powered Matches
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="matchingTabsContent">
                <div class="tab-pane fade <?php echo $activeTab === 'recommended' ? 'show active' : ''; ?>" id="recommended" role="tabpanel" aria-labelledby="recommended-tab">
                    <div class="filter-card">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="recommended">
                            <div class="col-md-6">
                                <label for="job_id" class="form-label fw-semibold">
                                    <i class="fas fa-briefcase me-2 text-primary"></i>Select Job Posting
                                </label>
                                <select class="form-select" name="job_id" id="job_id" style="border-radius: 10px; border: 2px solid #e5e7eb;">
                                    <?php foreach ($jobs as $job): ?>
                                        <option value="<?php echo $job['id']; ?>" <?php echo ((string) $job['id'] === (string) $selectedJobId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="min_score" class="form-label fw-semibold">
                                    <i class="fas fa-percentage me-2 text-primary"></i>Compatibility Level
                                </label>
                                <select class="form-select" name="min_score" id="min_score" disabled style="border-radius: 10px; border: 2px solid #e5e7eb;">
                                    <option value="100" selected>100% Perfect Match</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn w-100" style="background: var(--gradient-success); border: none; color: white; border-radius: 10px; padding: 10px;">
                                    <i class="fas fa-filter me-1"></i>Apply
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="results-card">
                        <div class="card-header">
                            <i class="fas fa-user-check me-2"></i>
                            Top Matched Applicants
                            <?php if ($selectedJob): ?>
                                <span class="opacity-90">for "<?php echo htmlspecialchars($selectedJob['title']); ?>"</span>
                            <?php endif; ?>
                            <span class="stats-badge"><?php echo count($recommendedCandidates); ?> candidates</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recommendedCandidates)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <h5 class="text-muted mt-3">No Matches Found</h5>
                                    <p class="text-muted">No candidates with matching skills and experience level yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="padding-left: 1.5rem;">Candidate Profile</th>
                                                <th>Technical Skills</th>
                                                <th>Experience Level</th>
                                                <th>Compatibility Score</th>
                                                <th style="padding-right: 1.5rem;">Quick Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recommendedCandidates as $candidate): ?>
                                                <tr class="candidate-card">
                                                    <td style="padding-left: 1.5rem;">
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            $profileImage = $candidate['profile_picture'] ?? null;
                                                            $imagePath = $profileImage && file_exists('../' . $profileImage) ? '../' . $profileImage : null;
                                                            ?>
                                                            <?php if ($imagePath): ?>
                                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Profile" class="candidate-avatar me-3">
                                                            <?php else: ?>
                                                                <div class="candidate-avatar bg-gradient d-flex align-items-center justify-content-center me-3" style="background: var(--gradient-primary);">
                                                                    <i class="fas fa-user text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong class="d-block"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-id-card me-1"></i>
                                                                    <?php echo htmlspecialchars($candidate['employee_id']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo htmlspecialchars($candidate['skills'] ?: 'Not specified'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-white">
                                                            <?php echo htmlspecialchars($candidate['experience_level'] ?: 'Not specified'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="score-badge excellent">
                                                            <i class="fas fa-check-circle"></i>
                                                            <?php echo $candidate['match_score']; ?>%
                                                        </span>
                                                    </td>
                                                    <td style="padding-right: 1.5rem;">
                                                        <div class="btn-group" role="group">
                                                            <a href="employee-documents.php" class="btn btn-sm btn-action btn-outline-primary">
                                                                <i class="fas fa-file-alt"></i>
                                                            </a>
                                                            <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm btn-action" style="background: var(--gradient-success); border: none; color: white;">
                                                                <i class="fas fa-envelope"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?php echo $activeTab === 'bestfit' ? 'show active' : ''; ?>" id="bestfit" role="tabpanel" aria-labelledby="bestfit-tab">
                    <div class="filter-card">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="bestfit">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-briefcase me-2 text-primary"></i>Active Job Postings
                                </label>
                                <div class="form-control-plaintext fw-bold" style="color: #667eea;">
                                    <?php echo count($jobs); ?> active posting(s)
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="best_min_score" class="form-label fw-semibold">
                                    <i class="fas fa-percentage me-2 text-primary"></i>Compatibility Level
                                </label>
                                <select class="form-select" name="best_min_score" id="best_min_score" disabled style="border-radius: 10px; border: 2px solid #e5e7eb;">
                                    <option value="100" selected>100% Perfect Match</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn w-100" style="background: var(--gradient-success); border: none; color: white; border-radius: 10px; padding: 10px;">
                                    <i class="fas fa-filter me-1"></i>Apply
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="results-card">
                        <div class="card-header">
                            <i class="fas fa-star me-2"></i>
                            Perfect Fit Candidates
                            <span class="stats-badge"><?php echo count($bestFits); ?> candidates</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($bestFits)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <h5 class="text-muted mt-3">No Perfect Matches Found</h5>
                                    <p class="text-muted">No candidates with 100% exact match for skills and experience level yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="padding-left: 1.5rem;">Candidate Profile</th>
                                                <th>Ideal Position</th>
                                                <th>Technical Skills</th>
                                                <th>Experience Level</th>
                                                <th>Compatibility Score</th>
                                                <th style="padding-right: 1.5rem;">Quick Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bestFits as $candidate): ?>
                                                <tr class="candidate-card">
                                                    <td style="padding-left: 1.5rem;">
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            $profileImage = $candidate['profile_picture'] ?? null;
                                                            $imagePath = $profileImage && file_exists('../' . $profileImage) ? '../' . $profileImage : null;
                                                            ?>
                                                            <?php if ($imagePath): ?>
                                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Profile" class="candidate-avatar me-3">
                                                            <?php else: ?>
                                                                <div class="candidate-avatar bg-gradient d-flex align-items-center justify-content-center me-3" style="background: var(--gradient-primary);">
                                                                    <i class="fas fa-user text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong class="d-block"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-id-card me-1"></i>
                                                                    <?php echo htmlspecialchars($candidate['employee_id']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary text-white">
                                                            <i class="fas fa-briefcase me-1"></i>
                                                            <?php echo htmlspecialchars($candidate['best_job_title']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo htmlspecialchars($candidate['skills'] ?: 'Not specified'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-white">
                                                            <?php echo htmlspecialchars($candidate['experience_level'] ?: 'Not specified'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="score-badge excellent">
                                                            <i class="fas fa-check-circle"></i>
                                                            <?php echo $candidate['best_score']; ?>%
                                                        </span>
                                                    </td>
                                                    <td style="padding-right: 1.5rem;">
                                                        <div class="btn-group" role="group">
                                                            <a href="employee-documents.php" class="btn btn-sm btn-action btn-outline-primary">
                                                                <i class="fas fa-file-alt"></i>
                                                            </a>
                                                            <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm btn-action" style="background: var(--gradient-success); border: none; color: white;">
                                                                <i class="fas fa-envelope"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?php echo $activeTab === 'aimatch' ? 'show active' : ''; ?>" id="aimatch" role="tabpanel" aria-labelledby="aimatch-tab">
                    <div class="filter-card">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="aimatch">
                            <div class="col-md-6">
                                <label for="ai_job_id" class="form-label fw-semibold">
                                    <i class="fas fa-briefcase me-2 text-primary"></i>Select Job Posting
                                </label>
                                <select class="form-select" name="job_id" id="ai_job_id" style="border-radius: 10px; border: 2px solid #e5e7eb;">
                                    <?php foreach ($jobs as $job): ?>
                                        <option value="<?php echo $job['id']; ?>" <?php echo ((string) $job['id'] === (string) $selectedJobId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-brain me-2 text-primary"></i>AI Matching Mode
                                </label>
                                <div class="form-control-plaintext fw-bold" style="color: #667eea;">
                                    <i class="fas fa-magic me-1"></i>Intelligent Ranking
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn w-100" style="background: var(--gradient-primary); border: none; color: white; border-radius: 10px; padding: 10px;">
                                    <i class="fas fa-brain me-1"></i>Analyze
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="results-card">
                        <div class="card-header" style="background: var(--gradient-primary);">
                            <i class="fas fa-brain me-2"></i>
                            AI-Powered Matches
                            <?php if ($selectedJob): ?>
                                <span class="opacity-90">for "<?php echo htmlspecialchars($selectedJob['title']); ?>"</span>
                            <?php endif; ?>
                            <span class="stats-badge" style="background: rgba(255,255,255,0.2); color: white;"><?php echo count($aiCandidates); ?> candidates</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($aiCandidates)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <h5 class="text-muted mt-3">No AI Matches Found</h5>
                                    <p class="text-muted">AI matching requires candidate skills and experience data to generate scores.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="padding-left: 1.5rem;">Candidate Profile</th>
                                                <th>Technical Skills</th>
                                                <th>Experience Level</th>
                                                <th>AI Compatibility Score</th>
                                                <th style="padding-right: 1.5rem;">Quick Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aiCandidates as $candidate): 
                                                $score = $candidate['ai_score'];
                                                $scoreClass = $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : 'fair');
                                            ?>
                                                <tr class="candidate-card">
                                                    <td style="padding-left: 1.5rem;">
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            $profileImage = $candidate['profile_picture'] ?? null;
                                                            $imagePath = $profileImage && file_exists('../' . $profileImage) ? '../' . $profileImage : null;
                                                            ?>
                                                            <?php if ($imagePath): ?>
                                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Profile" class="candidate-avatar me-3">
                                                            <?php else: ?>
                                                                <div class="candidate-avatar bg-gradient d-flex align-items-center justify-content-center me-3" style="background: var(--gradient-primary);">
                                                                    <i class="fas fa-user text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong class="d-block"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-id-card me-1"></i>
                                                                    <?php echo htmlspecialchars($candidate['employee_id']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo htmlspecialchars($candidate['skills'] ?: 'Not specified'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-white">
                                                            <?php echo htmlspecialchars($candidate['experience_level'] ?: 'Not specified'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="score-badge <?php echo $scoreClass; ?>">
                                                            <i class="fas fa-<?php echo $score >= 80 ? 'check-circle' : ($score >= 60 ? 'check' : 'exclamation-circle'); ?>"></i>
                                                            <?php echo $candidate['ai_score']; ?>%
                                                        </span>
                                                    </td>
                                                    <td style="padding-right: 1.5rem;">
                                                        <div class="btn-group" role="group">
                                                            <a href="employee-documents.php" class="btn btn-sm btn-action btn-outline-primary">
                                                                <i class="fas fa-file-alt"></i>
                                                            </a>
                                                            <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm btn-action" style="background: var(--gradient-success); border: none; color: white;">
                                                                <i class="fas fa-envelope"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
