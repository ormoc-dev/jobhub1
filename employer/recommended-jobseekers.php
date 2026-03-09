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
$minScore = isset($_GET['min_score']) ? max(0, min(100, (int) $_GET['min_score'])) : 0;

$selectedJob = null;
foreach ($jobs as $job) {
    if ((string) $job['id'] === (string) $selectedJobId) {
        $selectedJob = $job;
        break;
    }
}

$candidatesWithScores = [];
if ($selectedJob) {
    $candidateStmt = $pdo->prepare("SELECT u.id as user_id, u.email, u.profile_picture, u.status as user_status,
                                           ep.*
                                    FROM users u
                                    JOIN employee_profiles ep ON u.id = ep.user_id
                                    WHERE u.role = 'employee' AND u.status = 'active'");
    $candidateStmt->execute();
    $candidates = $candidateStmt->fetchAll();

    foreach ($candidates as $candidate) {
        $score = computeMatchScore($selectedJob, $candidate);
        if ($score >= $minScore) {
            $candidate['match_score'] = $score;
            $candidatesWithScores[] = $candidate;
        }
    }

    usort($candidatesWithScores, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommended Jobseekers - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-user-check me-2"></i>Recommended Jobseekers</h1>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle me-2"></i>
                You don't have any active job postings yet. 
                <a href="post-job.php" class="alert-link">Post a job</a> to get recommendations.
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="job_id" class="form-label">Select Job Posting</label>
                            <select class="form-select" name="job_id" id="job_id">
                                <?php foreach ($jobs as $job): ?>
                                    <option value="<?php echo $job['id']; ?>" <?php echo ((string) $job['id'] === (string) $selectedJobId) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                    <h5 class="mb-0">
                        Recommended Candidates
                        <?php if ($selectedJob): ?>
                            <span class="text-muted">for "<?php echo htmlspecialchars($selectedJob['title']); ?>"</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($candidatesWithScores)): ?>
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
                                        <th>Skills</th>
                                        <th>Experience</th>
                                        <th>Location</th>
                                        <th>Match Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidatesWithScores as $candidate): ?>
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
                                            <td><?php echo htmlspecialchars($candidate['skills'] ?: 'Not specified'); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['experience_level'] ?: 'Not specified'); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['location'] ?: 'Not specified'); ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $candidate['match_score']; ?>%</span>
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
