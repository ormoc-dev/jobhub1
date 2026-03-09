<?php
include '../config.php';
requireRole('employer');

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// Get job applications for this company (show all uploaded docs)
$stmt = $pdo->prepare("SELECT ja.*, ja.resume, ja.id_document, ja.tor_document, ja.employment_certificate, ja.seminar_certificate, ja.cover_letter, ja.certificate_of_attachment, ja.certificate_of_reports, ja.certificate_of_good_standing, ja.applied_date, ja.status as application_status,
                              ep.first_name, ep.last_name, ep.document1, ep.document2,
                              u.email, u.username,
                              jp.title as job_title, jp.id as job_id
                       FROM job_applications ja
                       JOIN employee_profiles ep ON ja.employee_id = ep.id
                       JOIN users u ON ep.user_id = u.id
                       JOIN job_postings jp ON ja.job_id = jp.id
                       WHERE jp.company_id = ?
                       ORDER BY ja.applied_date DESC");
$stmt->execute([$company['id']]);
$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employment Records - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="row">
            <div class="col-md-12 ms-sm-auto px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-alt me-2"></i>Hiring Reports
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="applications.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Applications
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Applications List -->
                <?php if (empty($applications)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No applicant documents found yet.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($applications as $app): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong><i class="fas fa-briefcase me-2"></i>Job:</strong> 
                                            <span class="badge bg-info"><?php echo htmlspecialchars($app['job_title']); ?></span>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <strong>Email:</strong><br>
                                                <small><?php echo htmlspecialchars($app['email']); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <strong>Applied:</strong><br>
                                                <small><?php echo date('M j, Y', strtotime($app['applied_date'])); ?></small>
                                            </div>
                                        </div>

                                        <hr>

                                        <!-- Documents Section -->
                                        <h6 class="mb-3">
                                            <i class="fas fa-folder-open me-2"></i>Downloadable Documents
                                        </h6>

                                        <div class="row">
                                            <?php 
                                            // Helper function to display a document
                                            $displayDoc = function($doc_path, $doc_name, $doc_type, $app_id, $border_class) {
                                                if (empty($doc_path)) return;
                                                $doc_exists = file_exists(__DIR__ . '/../' . $doc_path);
                                                $full_path = '../' . $doc_path;
                                                $doc_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                                $is_image = in_array($doc_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                                $is_pdf = ($doc_ext === 'pdf');
                                                return '<div class="col-12 mb-3">
                                                    <div class="card border ' . $border_class . '">
                                                        <div class="card-body">
                                                            <h6 class="card-title"><i class="fas fa-file me-2"></i>' . $doc_name . '</h6>
                                                            <p class="text-muted small mb-2">' . htmlspecialchars(basename($doc_path)) . '</p>' .
                                                            ($is_image && $doc_exists ? '<img src="' . htmlspecialchars($full_path) . '" alt="' . htmlspecialchars($doc_name) . '" class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;">' : '') .
                                                            ($is_pdf ? '<i class="fas fa-file-pdf fa-3x text-danger"></i>' : '<i class="fas fa-file fa-3x text-muted"></i>') .
                                                            '<br><br>' .
                                                            ($doc_exists ? '<div class="btn-group">
                                                                <a href="' . htmlspecialchars($full_path) . '" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-eye me-1"></i>View</a>
                                                                <a href="' . htmlspecialchars($full_path) . '" download class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>
                                                                <a href="document-verification.php?application_id=' . $app_id . '&doc_type=' . $doc_type . '" class="btn btn-sm btn-success" title="Verify Document"><i class="fas fa-check-circle me-1"></i>Verify</a>
                                                            </div>' : '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-exclamation-triangle me-1"></i>File Not Found</button>') .
                                                        '</div></div></div>';
                                            };
                                            
                                            $has_docs = false;

                                            // Show application documents from job-details.php
                                            $doc_html = $displayDoc($app['cover_letter'] ?? '', 'Cover Letter', 'cover_letter', $app['id'], 'border-secondary');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }

                                            $doc_html = $displayDoc($app['resume'] ?? '', 'Resume', 'resume', $app['id'], 'border-primary');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }
                                            
                                            $doc_html = $displayDoc($app['id_document'] ?? '', 'ID Document', 'id_document', $app['id'], 'border-success');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }
                                            
                                            $doc_html = $displayDoc($app['tor_document'] ?? '', 'TOR Document', 'tor_document', $app['id'], 'border-info');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }
                                            
                                            $doc_html = $displayDoc($app['employment_certificate'] ?? '', 'Employment Certificate', 'employment_certificate', $app['id'], 'border-warning');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }
                                            
                                            $doc_html = $displayDoc($app['seminar_certificate'] ?? '', 'Seminar Certificate', 'seminar_certificate', $app['id'], 'border-dark');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }

                                            $doc_html = $displayDoc($app['certificate_of_attachment'] ?? '', 'Certificate of Attachment', 'certificate_of_attachment', $app['id'], 'border-info');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }

                                            $doc_html = $displayDoc($app['certificate_of_reports'] ?? '', 'Certificate of Reports', 'certificate_of_reports', $app['id'], 'border-primary');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }

                                            $doc_html = $displayDoc($app['certificate_of_good_standing'] ?? '', 'Certificate of Good Standing', 'certificate_of_good_standing', $app['id'], 'border-success');
                                            if ($doc_html) { echo $doc_html; $has_docs = true; }
                                            
                                            // Then show profile documents (document1 and document2)
                                            if (!empty($app['document1'])):
                                                $doc1_exists = file_exists(__DIR__ . '/../' . $app['document1']); 
                                                $doc1_path = '../' . $app['document1'];
                                                $doc1_ext = strtolower(pathinfo($app['document1'], PATHINFO_EXTENSION));
                                                $is_image1 = in_array($doc1_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                                $is_pdf1 = ($doc1_ext === 'pdf');
                                                $has_docs = true;
                                            ?>
                                            <div class="col-12 mb-3">
                                                <div class="card border border-info">
                                                    <div class="card-body">
                                                        <h6 class="card-title">
                                                            <i class="fas fa-file me-2"></i>Profile Document 1 (Resume/CV)
                                                        </h6>
                                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars(basename($app['document1'])); ?></p>
                                                        <?php if ($is_image1 && $doc1_exists): ?>
                                                            <img src="<?php echo htmlspecialchars($doc1_path); ?>" alt="Document 1" class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                                        <?php elseif ($is_pdf1): ?>
                                                            <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-file fa-3x text-muted"></i>
                                                        <?php endif; ?>
                                                        <br><br>
                                                        <?php if ($doc1_exists): ?>
                                                            <div class="btn-group">
                                                                <a href="<?php echo htmlspecialchars($doc1_path); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-eye me-1"></i>View
                                                                </a>
                                                                <a href="<?php echo htmlspecialchars($doc1_path); ?>" download class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-download me-1"></i>Download
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled>
                                                                <i class="fas fa-exclamation-triangle me-1"></i>File Not Found
                                                            </button>
                                                            <span class="text-danger small d-block mt-2">The file is registered but not found on server</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php 
                                            // Show Document 2 (Other from employee profile)
                                            if (!empty($app['document2'])):
                                                $doc2_exists = file_exists(__DIR__ . '/../' . $app['document2']);
                                                $doc2_path = '../' . $app['document2'];
                                                $doc2_ext = strtolower(pathinfo($app['document2'], PATHINFO_EXTENSION));
                                                $is_image2 = in_array($doc2_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                                $is_pdf2 = ($doc2_ext === 'pdf');
                                                $has_docs = true;
                                            ?>
                                            <div class="col-12 mb-3">
                                                <div class="card border border-success">
                                                    <div class="card-body">
                                                        <h6 class="card-title">
                                                            <i class="fas fa-file-alt me-2"></i>Document 2 (Other)
                                                        </h6>
                                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars(basename($app['document2'])); ?></p>
                                                        <?php if ($is_image2 && $doc2_exists): ?>
                                                            <img src="<?php echo htmlspecialchars($doc2_path); ?>" alt="Document 2" class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                                        <?php elseif ($is_pdf2): ?>
                                                            <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-file fa-3x text-muted"></i>
                                                        <?php endif; ?>
                                                        <br><br>
                                                        <?php if ($doc2_exists): ?>
                                                            <div class="btn-group">
                                                                <a href="<?php echo htmlspecialchars($doc2_path); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-eye me-1"></i>View
                                                                </a>
                                                                <a href="<?php echo htmlspecialchars($doc2_path); ?>" download class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-download me-1"></i>Download
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled>
                                                                <i class="fas fa-exclamation-triangle me-1"></i>File Not Found
                                                            </button>
                                                            <span class="text-danger small d-block mt-2">The file is registered but not found on server</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!$has_docs): ?>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>No documents available for this application
                                                </div>
                                            </div>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

