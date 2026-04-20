<?php
include '../config.php';
requireRole('employee');

$stmt = $pdo->prepare("SELECT ep.* FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

if (isset($_POST['unsave_job'])) {
    $job_id = (int) ($_POST['job_id'] ?? 0);
    if ($job_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
        $stmt->execute([$profile['id'], $job_id]);
    }
    header('Location: saved-jobs.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs sj
                       JOIN job_postings jp ON sj.job_id = jp.id
                       WHERE sj.employee_id = ? AND jp.status = 'active'");
$stmt->execute([$profile['id']]);
$total_jobs = (int) $stmt->fetchColumn();
$total_pages = $total_jobs ? (int) ceil($total_jobs / $per_page) : 1;

$stmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, jc.category_name,
                       sj.saved_date, jp.posted_date,
                       (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND employee_id = ?) AS applied
                       FROM saved_jobs sj
                       JOIN job_postings jp ON sj.job_id = jp.id
                       JOIN companies c ON jp.company_id = c.id
                       LEFT JOIN job_categories jc ON jp.category_id = jc.id
                       WHERE sj.employee_id = ? AND jp.status = 'active'
                       ORDER BY sj.saved_date DESC
                       LIMIT " . $per_page . " OFFSET " . $offset);
$stmt->execute([$profile['id'], $profile['id']]);
$saved_jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/modern.css" rel="stylesheet">
    <style>
        .job-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1rem;
            background: #fff;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .job-card:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: #cbd5e1;
        }
        .job-card .job-title {
            color: #0f172a;
            font-weight: 600;
            text-decoration: none;
        }
        .job-card .job-title:hover {
            color: #2563eb;
        }
        .company-logo {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .saved-jobs-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
        }
    </style>
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employee-main-content">
        <div class="hero-section">
            <div class="hero-content">
                <div class="row align-items-center g-4">
                    <div class="col-lg">
                        <p class="hero-eyebrow mb-1">Shortlist</p>
                        <h1 class="hero-title mb-2">
                            <i class="fas fa-bookmark text-primary me-2 fs-5 align-middle"></i>Saved jobs
                        </h1>
                        <p class="hero-lead mb-0">Keep roles you like in one place—review details, apply when you are ready, or remove them anytime.</p>
                    </div>
                    <div class="col-lg-auto">
                        <a href="jobs.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-search me-2"></i>Browse more jobs
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-container">
        <div class="saved-jobs-summary mb-4">
            <p class="applications-kpi-label">Overview</p>
            <div class="stats-grid stats-grid--saved-jobs">
                <div class="stat-card stat-card--kpi primary">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?php echo (int) $total_jobs; ?></div>
                            <div class="stat-card-label">Saved jobs</div>
                        </div>
                        <div class="stat-card-icon blue">
                            <i class="fas fa-heart"></i>
                        </div>
                    </div>
                </div>
                <div class="messages-tip-card">
                    <i class="fas fa-info-circle text-primary flex-shrink-0 mt-1"></i>
                    <p class="mb-0">Save openings from search or job details. You can apply later from here or from each job page—nothing is submitted until you choose.</p>
                </div>
            </div>
        </div>

        <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Your saved jobs (<?php echo (int) $total_jobs; ?>)</h5>
                        <?php if ($total_jobs > 0): ?>
                            <small class="text-muted">Page <?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($saved_jobs)): ?>
                            <div class="text-center py-5 px-3 saved-jobs-empty">
                                <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No saved jobs yet</h5>
                                <p class="text-muted mb-4">Save roles from job search or a job’s detail page—they will show up here.</p>
                                <a href="jobs.php" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-search me-2"></i>Browse jobs
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($saved_jobs as $job): ?>
                                <div class="job-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-start">
                                                <?php if (!empty($job['company_logo'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Company Logo" class="company-logo me-3">
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1">
                                                        <a href="../job-details.php?id=<?php echo (int)$job['id']; ?>" class="job-title"><?php echo htmlspecialchars($job['title']); ?></a>
                                                        <?php if ($job['applied']): ?>
                                                            <span class="badge bg-success ms-2">Applied</span>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <p class="company-name mb-1"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                                    <div class="text-muted small mb-2">
                                                        <span class="me-3"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location'] ?? '-'); ?></span>
                                                        <span class="me-3"><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($job['employment_type'] ?? '-'); ?></span>
                                                        <?php if (!empty($job['category_name'])): ?>
                                                            <span class="me-3"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($job['category_name']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($job['salary_range'])): ?>
                                                        <div class="fw-bold mb-2" style="color: #059669;">
                                                            <i class="fas fa-peso-sign me-1"></i><?php echo htmlspecialchars($job['salary_range']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="text-muted small">Saved on <?php echo date('M j, Y', strtotime($job['saved_date'])); ?></div>
                                                </div>
                                            </div>
                                            <?php if (!empty($job['description'])): ?>
                                                <div class="mt-2">
                                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars(substr(strip_tags($job['description']), 0, 150)); ?>…</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="d-flex flex-column gap-2">
                                                <a href="../job-details.php?id=<?php echo (int)$job['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                                <?php if (!$job['applied']): ?>
                                                    <a href="../job-details.php?id=<?php echo (int)$job['id']; ?>#apply" class="btn btn-sm btn-success">
                                                        <i class="fas fa-paper-plane me-1"></i>Apply Now
                                                    </a>
                                                <?php else: ?>
                                                    <a href="applications.php" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-check me-1"></i>View Application
                                                    </a>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this job from saved?');">
                                                    <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                                    <button type="submit" name="unsave_job" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-heart-broken me-1"></i>Remove
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="mt-2 text-muted small">
                                                <i class="fas fa-clock me-1"></i>Posted <?php echo timeAgo($job['posted_date']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Saved jobs pagination" class="mt-4">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a></li>
                                        <?php endif; ?>
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                                        <?php endfor; ?>
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
        </div><!-- /content-container -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
