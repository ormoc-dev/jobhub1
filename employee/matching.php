<?php
include '../config.php';
requireRole('employee');
include '../employer/includes/matching.php';

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.*, u.email FROM employee_profiles ep 
                       JOIN users u ON ep.user_id = u.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

$employeeId = $profile['id'] ?? 0;

// Get all active jobs
$jobsStmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, jc.category_name,
                                  CASE WHEN sj.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
                                  (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND employee_id = ?) as has_applied
                           FROM job_postings jp 
                           JOIN companies c ON jp.company_id = c.id 
                           LEFT JOIN job_categories jc ON jp.category_id = jc.id
                           LEFT JOIN saved_jobs sj ON jp.id = sj.job_id AND sj.employee_id = ?
                          WHERE jp.status = 'active' AND c.status = 'active'
                           ORDER BY jp.posted_date DESC");
$jobsStmt->execute([$employeeId, $employeeId]);
$jobs = $jobsStmt->fetchAll();

// Calculate match scores for all jobs
$matchedJobs = [];
if (!empty($jobs)) {
    foreach ($jobs as $job) {
        $score = computeMatchScore($job, $profile);
        $job['match_score'] = $score;
        $matchedJobs[] = $job;
    }
    
    // Sort by match score descending
    usort($matchedJobs, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
    
    // Get top 6 best matches
    $bestMatches = array_slice($matchedJobs, 0, 6);
    
    // Get recommended jobs (next 6 jobs after best matches, or jobs with score >= 30)
    $recommendedJobs = [];
    $recommendedCount = 0;
    foreach ($matchedJobs as $job) {
        if ($recommendedCount >= 6) break;
        // Skip jobs already in best matches
        $isInBestMatches = false;
        foreach ($bestMatches as $bestJob) {
            if ($bestJob['id'] == $job['id']) {
                $isInBestMatches = true;
                break;
            }
        }
        if (!$isInBestMatches && $job['match_score'] >= 30) {
            $recommendedJobs[] = $job;
            $recommendedCount++;
        }
    }
} else {
    $bestMatches = [];
    $recommendedJobs = [];
}

// Calculate overall skill compatibility (average of top matches)
$overallCompatibility = 0;
if (!empty($bestMatches)) {
    $totalScore = 0;
    foreach ($bestMatches as $job) {
        $totalScore += $job['match_score'];
    }
    $overallCompatibility = round($totalScore / count($bestMatches));
}

// Get employee skills for display
$employeeSkills = !empty($profile['skills']) ? explode(',', $profile['skills']) : [];
$employeeSkills = array_map('trim', $employeeSkills);

// Career suggestions based on top matching categories
$careerSuggestions = [];
if (!empty($bestMatches)) {
    $categoryCounts = [];
    foreach ($bestMatches as $job) {
        $category = $job['category_name'] ?? 'General';
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = 0;
        }
        $categoryCounts[$category]++;
    }
    arsort($categoryCounts);
    $careerSuggestions = array_slice(array_keys($categoryCounts), 0, 5);
}

function getScoreColor($score) {
    if ($score >= 80) return '#10b981'; // Green
    if ($score >= 60) return '#3b82f6'; // Blue
    if ($score >= 40) return '#f59e0b'; // Amber
    return '#6b7280'; // Gray
}

function getScoreBadgeClass($score) {
    if ($score >= 80) return 'bg-success';
    if ($score >= 60) return 'bg-info';
    if ($score >= 40) return 'bg-warning text-dark';
    return 'bg-secondary';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Matching - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/modern.css" rel="stylesheet">
    <style>
        .matching-hero {
            background: linear-gradient(135deg, #7c3aed 0%, #4c1d95 50%, #2d1b69 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(124, 58, 237, 0.3);
        }

        .matching-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .matching-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .match-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
        }

        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(124, 58, 237, 0.2);
        }

        .match-card-header {
            background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
            color: white;
            padding: 20px;
            border: none;
        }

        .match-card-header h5 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .match-card-header i {
            font-size: 1.5rem;
        }

        .compatibility-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #e9d5ff;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .compatibility-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #7c3aed, #a78bfa, #c4b5fd);
        }

        .compatibility-score {
            font-size: 4rem;
            font-weight: 700;
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 20px 0;
        }

        .skill-item {
            display: inline-block;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
            color: #5b21b6;
            padding: 8px 16px;
            border-radius: 20px;
            margin: 5px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid #c4b5fd;
        }

        .job-match-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .job-match-item:hover {
            border-color: #a78bfa;
            box-shadow: 0 4px 15px rgba(167, 139, 250, 0.15);
            transform: translateX(5px);
        }

        .match-progress {
            height: 8px;
            border-radius: 10px;
            background: #e5e7eb;
            overflow: hidden;
            margin-top: 10px;
        }

        .match-progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .career-suggestion-item {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            border-left: 4px solid #7c3aed;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .career-suggestion-item:hover {
            background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
        }

        .career-suggestion-item i {
            color: #7c3aed;
            margin-right: 10px;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .company-logo-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
            flex-shrink: 0;
        }
        
        .badge-sm {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        .company-logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #7c3aed;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .matching-hero {
                padding: 30px 20px;
            }

            .matching-hero h1 {
                font-size: 2rem;
            }

            .compatibility-score {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employee-main-content">
        <!-- Hero Section -->
        <div class="matching-hero">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-magic me-3"></i>Smart Job Matching</h1>
                    <p>Discover opportunities perfectly aligned with your skills and career goals</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="stat-badge">
                        <i class="fas fa-briefcase"></i>
                        <span><?php echo count($jobs); ?> Active Jobs</span>
                    </div>
                    <div class="stat-badge">
                        <i class="fas fa-star"></i>
                        <span><?php echo count($bestMatches); ?> Top Matches</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skill Compatibility Score Section -->
        <div class="row g-4 mb-4">
            <div class="col-md-12">
                <div class="compatibility-card">
                    <h3 class="mb-3"><i class="fas fa-chart-line text-primary"></i> Skill Compatibility Score</h3>
                    <div class="compatibility-score"><?php echo $overallCompatibility; ?>%</div>
                    <p class="text-muted mb-4">Based on your profile and top job matches</p>
                    
                    <div class="match-progress mb-4">
                        <div class="match-progress-bar" 
                             style="width: <?php echo $overallCompatibility; ?>%; background: linear-gradient(90deg, <?php echo getScoreColor($overallCompatibility); ?> 0%, <?php echo getScoreColor($overallCompatibility); ?>dd 100%);">
                        </div>
                    </div>

                    <?php if (!empty($employeeSkills)): ?>
                        <div class="mt-4">
                            <h5 class="mb-3">Your Skills</h5>
                            <?php foreach ($employeeSkills as $skill): ?>
                                <span class="skill-item"><?php echo htmlspecialchars($skill); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Complete your profile with skills to get better matching scores.
                            <a href="profile.php" class="alert-link">Update Profile</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row g-4 mb-4">
            <!-- Best Job Matches -->
            <div class="col-lg-6">
                <div class="card match-card">
                    <div class="match-card-header">
                        <h5>
                            <i class="fas fa-bullseye"></i>
                            Best Job Matches
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bestMatches)): ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h5>No matches found</h5>
                                <p>Complete your profile or check back later for new opportunities.</p>
                                <a href="profile.php" class="btn btn-primary btn-sm mt-3">
                                    <i class="fas fa-user-edit me-2"></i>Complete Profile
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($bestMatches as $job): ?>
                                <div class="job-match-item">
                                    <div class="d-flex align-items-start">
                                        <div class="company-logo-wrapper me-3">
                                            <?php if ($job['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-building fa-lg" style="color: #7c3aed;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                            </h6>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company_name']); ?>
                                                <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                            </p>
                                            <div class="match-progress mb-2">
                                                <div class="match-progress-bar" 
                                                     style="width: <?php echo $job['match_score']; ?>%; background: linear-gradient(90deg, <?php echo getScoreColor($job['match_score']); ?> 0%, <?php echo getScoreColor($job['match_score']); ?>dd 100%);">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge <?php echo getScoreBadgeClass($job['match_score']); ?> badge-sm">
                                                    <?php echo $job['match_score']; ?>% Match
                                                </span>
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="recommended.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-right me-2"></i>View All
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recommended Jobs -->
            <div class="col-lg-6">
                <div class="card match-card">
                    <div class="match-card-header" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <h5>
                            <i class="fas fa-star"></i>
                            Recommended Jobs
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recommendedJobs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-star"></i>
                                <h5>No recommendations yet</h5>
                                <p>Complete your profile to get personalized job recommendations.</p>
                                <a href="profile.php" class="btn btn-primary btn-sm mt-3">
                                    <i class="fas fa-user-edit me-2"></i>Complete Profile
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recommendedJobs as $job): ?>
                                <div class="job-match-item">
                                    <div class="d-flex align-items-start">
                                        <div class="company-logo-wrapper me-3">
                                            <?php if ($job['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-building fa-lg" style="color: #3b82f6;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                            </h6>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company_name']); ?>
                                                <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                            </p>
                                            <div class="match-progress mb-2">
                                                <div class="match-progress-bar" 
                                                     style="width: <?php echo $job['match_score']; ?>%; background: linear-gradient(90deg, <?php echo getScoreColor($job['match_score']); ?> 0%, <?php echo getScoreColor($job['match_score']); ?>dd 100%);">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge <?php echo getScoreBadgeClass($job['match_score']); ?> badge-sm">
                                                    <?php echo $job['match_score']; ?>% Match
                                                </span>
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="recommended.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-right me-2"></i>View All
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Career Suggestions Section -->
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card match-card">
                    <div class="match-card-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <h5>
                            <i class="fas fa-lightbulb"></i>
                            Career Suggestions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <?php if (empty($careerSuggestions)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-lightbulb fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                                        <p class="text-muted">Complete your profile to get personalized career suggestions.</p>
                                        <a href="profile.php" class="btn btn-sm btn-outline-success">Update Profile</a>
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($careerSuggestions as $suggestion): ?>
                                            <div class="col-md-6">
                                                <div class="career-suggestion-item">
                                                    <i class="fas fa-arrow-right"></i>
                                                    <strong><?php echo htmlspecialchars($suggestion); ?></strong>
                                                    <p class="mb-0 text-muted small mt-1">Based on your top matches</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <div class="border-start ps-4">
                                    <h6 class="mb-3"><i class="fas fa-chart-bar me-2 text-success"></i>Tips to Improve Matching</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Complete your profile with all skills
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Add your work experience details
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Upload your resume/CV
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Keep your profile updated
                                        </li>
                                    </ul>
                                    <a href="profile.php" class="btn btn-success btn-sm w-100 mt-3">
                                        <i class="fas fa-user-edit me-2"></i>Update Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.match-progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>
