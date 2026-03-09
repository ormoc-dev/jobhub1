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

$minScore = isset($_GET['min_score']) ? max(0, min(100, (int) $_GET['min_score'])) : 0;

$bestFits = [];
if (!empty($jobs)) {
    $candidateStmt = $pdo->prepare("SELECT u.id as user_id, u.email, u.profile_picture, u.status as user_status,
                                           ep.*
                                    FROM users u
                                    JOIN employee_profiles ep ON u.id = ep.user_id
                                    WHERE u.role = 'employee' AND u.status = 'active'");
    $candidateStmt->execute();
    $candidates = $candidateStmt->fetchAll();

    foreach ($candidates as $candidate) {
        $candidateText = buildCandidateText($candidate);
        $bestScore = 0;
        $bestJob = null;

        foreach ($jobs as $job) {
            $jobText = buildJobText($job);
            $score = computeMatchScore($jobText, $candidateText);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestJob = $job;
            }
        }

        if ($bestJob && $bestScore >= $minScore) {
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
    <title>Best-fit Candidates - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-star me-2"></i>Best-fit Candidates</h1>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle me-2"></i>
                You don't have any active job postings yet. 
                <a href="post-job.php" class="alert-link">Post a job</a> to see best-fit candidates.
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Active Jobs</label>
                            <div class="form-control-plaintext">
                                <?php echo count($jobs); ?> active posting(s)
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="min_score" class="form-label">Minimum Match Score</label>
                            <select class="form-select" name="min_score" id="min_score">
                                <?php foreach ([0, 20, 40, 60, 80] as $scoreOption): ?>
                                    <option value="<?php echo $scoreOption; ?>" <?php echo $minScore === $scoreOption ? 'selected' : ''; ?>>
                                        <?php echo $scoreOption; ?>% and above
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn w-100" style="background: #10b981; border-color: #10b981; color: white;">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Candidates (<?php echo count($bestFits); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($bestFits)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No candidates found</h5>
                            <p class="text-muted">Try lowering the minimum match score.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Best-fit Job</th>
                                        <th>Skills</th>
                                        <th>Location</th>
                                        <th>Match Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bestFits as $candidate): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $profileImage = $candidate['profile_picture'] ?? null;
                                                    $imagePath = $profileImage && file_exists('../' . $profileImage) ? '../' . $profileImage : null;
                                                    ?>
                                                    <?php if ($imagePath): ?>
                                                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Profile" class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">ID: <?php echo htmlspecialchars($candidate['employee_id']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($candidate['best_job_title']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['skills'] ?: 'Not specified'); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['location'] ?: 'Not specified'); ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $candidate['best_score']; ?>%</span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="employee-documents.php" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-alt"></i> Documents
                                                    </a>
                                                    <a href="messages.php?user_id=<?php echo $candidate['user_id']; ?>" class="btn btn-sm" style="background: #10b981; border-color: #10b981; color: white;">
                                                        <i class="fas fa-envelope"></i> Message
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
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
