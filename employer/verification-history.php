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

// Get only verified document verifications for applicants to this company's jobs
$stmt = $pdo->prepare("SELECT dv.*, ep.first_name, ep.last_name, u.email,
                             u2.username as verified_by_username,
                             dv.document_type,
                             CASE 
                                WHEN LOWER(TRIM(dv.document_type)) = 'resume' THEN 'Resume'
                                WHEN LOWER(TRIM(dv.document_type)) = 'id_document' THEN 'ID Document'
                                WHEN LOWER(TRIM(dv.document_type)) = 'document1' THEN 'Document 1 (Resume/CV)'
                                WHEN LOWER(TRIM(dv.document_type)) = 'document2' THEN 'Document 2 (Other)'
                                WHEN LOWER(TRIM(dv.document_type)) = 'tor_document' THEN 'TOR Document'
                                WHEN LOWER(TRIM(dv.document_type)) = 'employment_certificate' THEN 'Employment Certificate'
                                WHEN LOWER(TRIM(dv.document_type)) = 'seminar_certificate' THEN 'Seminar Certificate'
                                WHEN dv.document_type IS NOT NULL AND dv.document_type != '' THEN CONCAT('Unknown: ', dv.document_type)
                                ELSE 'Unknown Document'
                             END as doc_type_name
                      FROM document_verifications dv
                      JOIN job_applications ja ON dv.application_id = ja.id
                      JOIN job_postings jp ON ja.job_id = jp.id
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      JOIN users u ON ep.user_id = u.id
                      JOIN users u2 ON dv.verified_by = u2.id
                      WHERE jp.company_id = ?
                        AND dv.verification_status = 'verified'
                      ORDER BY dv.verified_at DESC");
$stmt->execute([$company['id']]);
$verifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification History - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="row">
            <div class="col-md-12 ms-sm-auto px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-check-circle me-2"></i>Verified Document History
                    </h1>
                    <a href="employee-documents.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt me-1"></i>View Documents
                    </a>
                </div>

                <?php if (empty($verifications)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Verified Documents</h5>
                            <p class="text-muted">There are no verified documents yet.</p>
                            <a href="employee-documents.php" class="btn btn-primary">
                                <i class="fas fa-file-alt me-1"></i>Go to Employment Records
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Verified Documents
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Applicant</th>
                                            <th>Document Type</th>
                                            <th>Status</th>
                                            <th>Verified By</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($verifications as $ver): ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('M j, Y g:i A', strtotime($ver['verified_at'])); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ver['first_name'] . ' ' . $ver['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($ver['email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Get document type - first try SQL CASE result, then raw document_type
                                                    $doc_type_raw = trim($ver['document_type'] ?? '');
                                                    $doc_type_name = '';
                                                    
                                                    // First, try the SQL CASE result (doc_type_name)
                                                    if (!empty($ver['doc_type_name'])) {
                                                        $doc_type_name = trim($ver['doc_type_name']);
                                                    }
                                                    
                                                    // If SQL didn't provide a name, use PHP mapping on the raw document_type
                                                    if (empty($doc_type_name) && !empty($doc_type_raw)) {
                                                        // Normalize the document type (trim, lowercase for comparison)
                                                        $doc_type_normalized = strtolower(trim($doc_type_raw));
                                                        $doc_type_name = match($doc_type_normalized) {
                                                            'resume' => 'Resume',
                                                            'id_document' => 'ID Document',
                                                            'document1' => 'Document 1 (Resume/CV)',
                                                            'document2' => 'Document 2 (Other)',
                                                            'tor_document' => 'TOR Document',
                                                            'employment_certificate' => 'Employment Certificate',
                                                            'seminar_certificate' => 'Seminar Certificate',
                                                            default => ucfirst(str_replace('_', ' ', $doc_type_raw))
                                                        };
                                                    }
                                                    
                                                    // Final fallback
                                                    if (empty($doc_type_name)) {
                                                        $doc_type_name = 'Unknown Document';
                                                    }
                                                    ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-file-alt me-1"></i>
                                                        <?php echo htmlspecialchars($doc_type_name); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($ver['verification_status'] === 'verified'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i>Verified
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times me-1"></i>Rejected
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-user-shield me-1"></i>
                                                    <?php echo htmlspecialchars($ver['verified_by_username']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($ver['verification_notes']): ?>
                                                        <small><?php echo htmlspecialchars($ver['verification_notes']); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">No notes</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

