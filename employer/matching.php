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
        // AI matching can be more flexible, show candidates with at least some overlap
        $score = computeMatchScore($selectedJob, $candidate);
        if ($score >= 40) { // Show anyone with 40% or more basic match for AI to analyze
            $candidate['ai_score_placeholder'] = $score;
            $aiCandidates[] = $candidate;
        }
    }

    usort($aiCandidates, function ($a, $b) {
        return $b['ai_score_placeholder'] <=> $a['ai_score_placeholder'];
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
            --match-primary: #2563eb;
            --match-primary-dark: #1d4ed8;
            --match-success: #059669;
            --match-success-dark: #047857;
            --match-warning: #d97706;
            --match-border: #e2e8f0;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
            --shadow-md: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        .matching-header {
            background: #ffffff;
            color: #0f172a;
            padding: 1.75rem 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--match-border);
            box-shadow: var(--shadow-sm);
        }

        .matching-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
            color: #0f172a;
        }

        .matching-header h1 i {
            color: var(--match-primary);
        }

        .matching-header p {
            color: #64748b;
        }

        #matchingTabs.nav-pills {
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        #matchingTabs .nav-item {
            margin: 0;
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            padding: 0.65rem 1.25rem;
            font-weight: 500;
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
            border: 1px solid var(--match-border);
            background: #fff;
            color: #475569;
        }

        .nav-pills .nav-link.active {
            background: var(--match-primary);
            color: #fff !important;
            border-color: var(--match-primary);
            box-shadow: var(--shadow-sm);
        }

        .nav-pills .nav-link:hover:not(.active) {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .filter-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--match-border);
        }

        .results-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--match-border);
            overflow: hidden;
        }

        .results-card .card-header {
            background: #eff6ff;
            color: #1e3a8a;
            padding: 1rem 1.5rem;
            border: none;
            border-bottom: 1px solid #bfdbfe;
            font-weight: 600;
            font-size: 1rem;
        }

        .results-card .card-header i {
            color: var(--match-primary);
        }

        .results-card .card-header .opacity-90 {
            color: #334155;
            font-weight: 500;
        }

        .score-badge {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .score-badge.excellent {
            background: var(--match-success);
            color: white;
        }

        .score-badge.good {
            background: var(--match-primary);
            color: white;
        }

        .score-badge.fair {
            background: var(--match-warning);
            color: white;
        }

        .candidate-card {
            transition: background-color 0.15s ease;
            border-left: 3px solid transparent;
        }

        .candidate-card:hover {
            background: #f8fafc;
            border-left-color: var(--match-primary);
        }
        
        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            color: #475569;
            border-bottom: 2px solid var(--match-border);
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
            border: 2px solid var(--match-border);
            transition: border-color 0.2s ease;
        }

        .candidate-avatar:hover {
            border-color: var(--match-primary);
        }

        .candidate-avatar-fallback {
            background: var(--match-primary);
        }

        .btn-action {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-action:hover {
            box-shadow: var(--shadow-sm);
        }

        .btn-matching-apply {
            background: var(--match-success);
            color: #fff !important;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 600;
        }

        .btn-matching-apply:hover {
            background: var(--match-success-dark);
            color: #fff !important;
        }

        .btn-matching-analyze {
            background: var(--match-primary);
            color: #fff !important;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 600;
        }

        .btn-matching-analyze:hover {
            background: var(--match-primary-dark);
            color: #fff !important;
        }

        .btn-matching-message {
            background: var(--match-success);
            color: #fff !important;
            border: none;
        }

        .btn-matching-message:hover {
            background: var(--match-success-dark);
            color: #fff !important;
        }
        
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .stats-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
            border: 1px solid #bfdbfe;
        }

        .text-match-accent {
            color: var(--match-primary) !important;
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="matching-header">
            <h1><i class="fas fa-users-cog me-2"></i>Smart Candidate Matching</h1>
            <p class="mb-0 mt-2">Discover the perfect candidates for your job openings</p>
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
                                <button type="submit" class="btn btn-matching-apply w-100">
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
                                                                <div class="candidate-avatar candidate-avatar-fallback d-flex align-items-center justify-content-center me-3">
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
                                                            <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm btn-action btn-matching-message">
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
                                <div class="form-control-plaintext fw-bold text-match-accent">
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
                                <button type="submit" class="btn btn-matching-apply w-100">
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
                                                                <div class="candidate-avatar candidate-avatar-fallback d-flex align-items-center justify-content-center me-3">
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
                                                            <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm btn-action btn-matching-message">
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
                                <div class="form-control-plaintext fw-bold text-match-accent">
                                    <i class="fas fa-magic me-1"></i>Intelligent Ranking
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-matching-analyze w-100">
                                    <i class="fas fa-brain me-1"></i>Analyze
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="results-card">
                        <div class="card-header">
                            <i class="fas fa-brain me-2"></i>
                            AI-Powered Matches
                            <?php if ($selectedJob): ?>
                                <span class="opacity-90">for "<?php echo htmlspecialchars($selectedJob['title']); ?>"</span>
                            <?php endif; ?>
                            <span class="stats-badge"><?php echo count($aiCandidates); ?> candidates</span>
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
                                                <th>AI Deep Analysis</th>
                                                <th style="padding-right: 1.5rem;">Quick Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aiCandidates as $candidate): ?>
                                                 <tr class="candidate-card" id="row-<?php echo $candidate['user_id']; ?>">
                                                    <td style="padding-left: 1.5rem;">
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            $profileImage = $candidate['profile_picture'] ?? null;
                                                            $imagePath = $profileImage && file_exists('../' . $profileImage) ? '../' . $profileImage : null;
                                                            ?>
                                                            <?php if ($imagePath): ?>
                                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Profile" class="candidate-avatar me-3">
                                                            <?php else: ?>
                                                                <div class="candidate-avatar candidate-avatar-fallback d-flex align-items-center justify-content-center me-3">
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
                                                    <td class="ai-analysis-cell" style="max-width: 300px;">
                                                        <div class="ai-deep-info">
                                                            <button type="button" class="btn btn-sm btn-outline-primary btn-ai-analyze" onclick="performAiDeepMatch(<?php echo $selectedJobId; ?>, <?php echo $candidate['user_id']; ?>, this)">
                                                                <i class="fas fa-magic me-1"></i>Deep Analyze
                                                            </button>
                                                            <div class="ai-reasoning mt-1 small text-muted" style="display: none;"></div>
                                                        </div>
                                                    </td>
                                                    <td style="padding-right: 1.5rem;">
                                                        <div class="btn-group" role="group">
                                                            <a href="employee-documents.php" class="btn btn-sm btn-action btn-outline-primary">
                                                                <i class="fas fa-file-alt"></i>
                                                            </a>
                                                            <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm btn-action btn-matching-message">
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
    <script>
        async function performAiDeepMatch(jobId, userId, btn) {
            const cell = btn.closest('.ai-analysis-cell');
            const reasoningDiv = cell.querySelector('.ai-reasoning');
            const originalHtml = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Analyzing...';
            btn.disabled = true;
            reasoningDiv.style.display = 'none';

            try {
                const response = await fetch('generate_ai_match.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ job_id: jobId, user_id: userId })
                });

                const data = await response.json();
                
                if (data.success) {
                    const score = data.match_score;
                    const scoreClass = score >= 80 ? 'excellent' : (score >= 60 ? 'good' : 'fair');
                    const icon = score >= 80 ? 'check-circle' : (score >= 60 ? 'check' : 'exclamation-circle');
                    
                    btn.outerHTML = `
                        <span class="score-badge ${scoreClass}">
                            <i class="fas fa-${icon}"></i> AI Match: ${score}%
                        </span>
                    `;
                    
                    reasoningDiv.innerHTML = `<i class="fas fa-quote-left me-1"></i> ${data.reasoning}`;
                    reasoningDiv.style.display = 'block';
                    reasoningDiv.classList.add('fade-in');
                } else {
                    alert('AI Analysis failed: ' + (data.error || 'Unknown error'));
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Connection error during AI analysis.');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
    </script>
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ai-reasoning {
            border-left: 2px solid #2563eb;
            padding-left: 10px;
            font-style: italic;
            color: #4b5563 !important;
            line-height: 1.4;
        }
    </style>
</body>
</html>
