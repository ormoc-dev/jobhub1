<?php
include '../config.php';
requireRole('employer');

// Get application ID and document type from URL
$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
$doc_type = isset($_GET['doc_type']) ? sanitizeInput($_GET['doc_type']) : '';

// Allow verification for all supported application documents
$allowed_doc_types = ['resume', 'id_document', 'tor_document', 'employment_certificate', 'seminar_certificate', 'cover_letter', 'certificate_of_attachment', 'certificate_of_reports', 'certificate_of_good_standing'];

if (!$application_id || !in_array($doc_type, $allowed_doc_types)) {
    $_SESSION['error'] = 'Invalid parameters.';
    redirect('employee-documents.php');
}

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// Get application details
$stmt = $pdo->prepare("SELECT ja.*, ep.first_name, ep.last_name, u.email,
                             jp.title as job_title
                      FROM job_applications ja
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      JOIN users u ON ep.user_id = u.id
                      JOIN job_postings jp ON ja.job_id = jp.id
                      WHERE ja.id = ? AND jp.company_id = ?");
$stmt->execute([$application_id, $company['id']]);
$application = $stmt->fetch();

if (!$application) {
    $_SESSION['error'] = 'Application not found or access denied.';
    redirect('employee-documents.php');
}

// Get document path based on type
$document_path = '';
$document_name = '';
if ($doc_type === 'resume' && !empty($application['resume'])) {
    $document_path = '../' . $application['resume'];
    $document_name = 'Resume';
} elseif ($doc_type === 'id_document' && !empty($application['id_document'])) {
    $document_path = '../' . $application['id_document'];
    $document_name = 'ID Document';
} elseif ($doc_type === 'tor_document' && !empty($application['tor_document'])) {
    $document_path = '../' . $application['tor_document'];
    $document_name = 'TOR Document';
} elseif ($doc_type === 'employment_certificate' && !empty($application['employment_certificate'])) {
    $document_path = '../' . $application['employment_certificate'];
    $document_name = 'Employment Certificate';
} elseif ($doc_type === 'seminar_certificate' && !empty($application['seminar_certificate'])) {
    $document_path = '../' . $application['seminar_certificate'];
    $document_name = 'Seminar Certificate';
} elseif ($doc_type === 'cover_letter' && !empty($application['cover_letter'])) {
    $document_path = '../' . $application['cover_letter'];
    $document_name = 'Cover Letter';
} elseif ($doc_type === 'certificate_of_attachment' && !empty($application['certificate_of_attachment'])) {
    $document_path = '../' . $application['certificate_of_attachment'];
    $document_name = 'Certificate of Attachment';
} elseif ($doc_type === 'certificate_of_reports' && !empty($application['certificate_of_reports'])) {
    $document_path = '../' . $application['certificate_of_reports'];
    $document_name = 'Certificate of Reports';
} elseif ($doc_type === 'certificate_of_good_standing' && !empty($application['certificate_of_good_standing'])) {
    $document_path = '../' . $application['certificate_of_good_standing'];
    $document_name = 'Certificate of Good Standing';
} else {
    $_SESSION['error'] = 'Document not found.';
    redirect('employee-documents.php');
}

// Handle verification
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_document'])) {
    $verification_status = sanitizeInput($_POST['verification_status']);
    $verification_notes = sanitizeInput($_POST['verification_notes']);
    $post_application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $post_doc_type = isset($_POST['doc_type']) ? sanitizeInput($_POST['doc_type']) : '';
    
    if (!in_array($verification_status, ['verified', 'rejected']) || !$post_application_id || !in_array($post_doc_type, $allowed_doc_types)) {
        $error = 'Invalid parameters.';
    } else {
        try {
            // Insert verification record
            $stmt = $pdo->prepare("INSERT INTO document_verifications (application_id, document_type, verified_by, verification_status, verification_notes, verified_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$post_application_id, $post_doc_type, $_SESSION['user_id'], $verification_status, $verification_notes]);
            
            $_SESSION['message'] = 'Document verification recorded successfully!';
            redirect('verification-history.php');
        } catch (Exception $e) {
            $error = 'Failed to record verification: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Document - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="row">
            <div class="col-md-10 ms-sm-auto px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-check-circle me-2"></i>Verify Document
                    </h1>
                    <a href="employee-documents.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-file me-2"></i><?php echo htmlspecialchars($document_name); ?>
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <img src="<?php echo htmlspecialchars($document_path); ?>" alt="Document" class="img-fluid" style="max-height: 600px;">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Verification Status</label>
                                        <select name="verification_status" class="form-select" required>
                                            <option value="verified">✓ Verified</option>
                                            <option value="rejected">✗ Rejected</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea name="verification_notes" class="form-control" rows="5" placeholder="Add any notes about this verification..."></textarea>
                                    </div>
                                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                                    <input type="hidden" name="doc_type" value="<?php echo htmlspecialchars($doc_type); ?>">
                                    <button type="submit" name="verify_document" class="btn btn-success w-100">
                                        <i class="fas fa-check-circle me-1"></i>Record Verification
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

