<?php
include '../config.php';
requireRole('employer');

$message = '';
$error = '';

// Get company profile
$stmt = $pdo->prepare("SELECT c.*, u.email FROM companies c 
                       JOIN users u ON c.user_id = u.id 
                       WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $companyName = sanitizeInput($_POST['company_name']);
    $contactNumber = sanitizeInput($_POST['contact_number']);
    $contactEmail = sanitizeInput($_POST['contact_email']);
    $contactFirstName = sanitizeInput($_POST['contact_first_name']);
    $contactLastName = sanitizeInput($_POST['contact_last_name']);
    $contactPosition = sanitizeInput($_POST['contact_position']);
    $locationAddress = sanitizeInput($_POST['location_address']);
    $description = sanitizeInput($_POST['description']);
    
    if (empty($companyName) || empty($contactNumber) || empty($contactEmail) || empty($contactFirstName) || empty($contactLastName) || empty($contactPosition) || empty($locationAddress)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Handle file uploads
            $companyLogo = $company['company_logo'] ?? null;
            $businessPermit = $company['business_permit'] ?? null;
            
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
                $logoPath = uploadFile($_FILES['company_logo'], 'companies');
                if ($logoPath) {
                    $companyLogo = $logoPath;
                } else {
                    throw new Exception('Failed to upload company logo.');
                }
            }
            
            if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] == 0) {
                $permitPath = uploadFile($_FILES['business_permit'], 'companies');
                if ($permitPath) {
                    $businessPermit = $permitPath;
                } else {
                    throw new Exception('Failed to upload business permit.');
                }
            }
            
            if ($company) {
                // Update existing company
                $stmt = $pdo->prepare("UPDATE companies SET 
                                      company_name = ?, contact_number = ?, contact_email = ?, 
                                      contact_first_name = ?, contact_last_name = ?, contact_position = ?,
                                      location_address = ?, description = ?, 
                                      company_logo = ?, business_permit = ? 
                                      WHERE user_id = ?");
                $stmt->execute([
                    $companyName, $contactNumber, $contactEmail, $contactFirstName, $contactLastName, $contactPosition, $locationAddress, 
                    $description, $companyLogo, $businessPermit, 
                    $_SESSION['user_id']
                ]);
                
                // Update user's profile picture to match company logo
                if ($companyLogo) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$companyLogo, $_SESSION['user_id']]);
                }
            } else {
                // Create new company profile
                $stmt = $pdo->prepare("INSERT INTO companies 
                                      (user_id, company_name, contact_number, contact_email, 
                                       location_address, description, 
                                       company_logo, business_permit, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $_SESSION['user_id'], $companyName, $contactNumber, $contactEmail, 
                    $locationAddress, $description, $companyLogo, $businessPermit
                ]);
            }
            
            $pdo->commit();
            $message = 'Company profile updated successfully!';
            
            // Refresh company data
            $stmt = $pdo->prepare("SELECT c.*, u.email FROM companies c 
                                   JOIN users u ON c.user_id = u.id 
                                   WHERE c.user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $company = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/minimal.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h1><i class="fas fa-building me-2"></i>Company Profile</h1>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card cp-card">
                            <div class="card-header d-flex align-items-center">
                                <span class="cp-section-label section-info">
                                    <i class="fas fa-building"></i> Company Information
                                </span>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data" id="company-profile-form">
                                    <div class="cp-form-section">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="company_name" class="form-label">Company Name <span style="color: #ef4444;">*</span></label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                                   value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="contact_number" class="form-label">Contact Number <span style="color: #ef4444;">*</span></label>
                                            <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                                   value="<?php echo htmlspecialchars($company['contact_number'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contact_email" class="form-label">Contact Email <span style="color: #ef4444;">*</span></label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3 mt-4">
                                        <span class="cp-section-label section-info" style="font-size: 0.875rem; padding: 0.35rem 0.75rem;">
                                            <i class="fas fa-user-tie"></i> Contact Person
                                        </span>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="contact_first_name" class="form-label">First Name <span style="color: #ef4444;">*</span></label>
                                            <input type="text" class="form-control" id="contact_first_name" name="contact_first_name" 
                                                   value="<?php echo htmlspecialchars($company['contact_first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="contact_last_name" class="form-label">Last Name <span style="color: #ef4444;">*</span></label>
                                            <input type="text" class="form-control" id="contact_last_name" name="contact_last_name" 
                                                   value="<?php echo htmlspecialchars($company['contact_last_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="contact_position" class="form-label">Position <span style="color: #ef4444;">*</span></label>
                                            <input type="text" class="form-control" id="contact_position" name="contact_position" 
                                                   value="<?php echo htmlspecialchars($company['contact_position'] ?? ''); ?>" 
                                                   placeholder="e.g. HR Manager" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="location_address" class="form-label">Location Address <span style="color: #ef4444;">*</span></label>
                                            <textarea class="form-control" id="location_address" name="location_address" rows="3" required><?php echo htmlspecialchars($company['location_address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Company Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" 
                                                  placeholder="Describe your company, mission, and what makes you unique..."><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
                                    </div>
                                    </div>

                                    <div class="cp-form-section">
                                    <span class="cp-section-label section-branding mb-3 d-inline-flex">
                                        <i class="fas fa-palette"></i> Employer Branding
                                    </span>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="company_logo" class="form-label">Company Logo</label>
                                            <input type="file" class="form-control" id="company_logo" name="company_logo" accept="image/*">
                                            <?php if (!empty($company['company_logo'])): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">Current logo: <?php echo basename($company['company_logo']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="business_permit" class="form-label">Business Permit</label>
                                            <input type="file" class="form-control" id="business_permit" name="business_permit" 
                                                   accept=".pdf,.jpg,.jpeg,.png">
                                            <?php if (!empty($company['business_permit'])): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">Current permit: <?php echo basename($company['business_permit']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    </div>

                                    <div class="text-end pt-3">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Save Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card cp-card">
                            <div class="card-header d-flex align-items-center">
                                <span class="cp-section-label section-verification">
                                    <i class="fas fa-shield-halved"></i> Verification Status
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if ($company): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Current Status:</label>
                                        <div>
                                            <?php
                                            $statusClass = '';
                                            $statusIcon = '';
                                            switch ($company['status']) {
                                                case 'active':
                                                    $statusClass = 'success';
                                                    $statusIcon = 'check-circle';
                                                    break;
                                                case 'pending':
                                                    $statusClass = 'warning';
                                                    $statusIcon = 'clock';
                                                    break;
                                                case 'suspended':
                                                    $statusClass = 'danger';
                                                    $statusIcon = 'times-circle';
                                                    break;
                                                default:
                                                    $statusClass = 'secondary';
                                                    $statusIcon = 'question-circle';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?> fs-6">
                                                <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                                <?php echo ucfirst($company['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if ($company['status'] === 'pending'): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Pending Approval</strong><br>
                                            Your company profile is under review. You will be notified once approved.
                                        </div>
                                    <?php elseif ($company['status'] === 'active'): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Profile Approved</strong><br>
                                            Your company profile is active and you can post jobs.
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label">Profile Completion:</label>
                                        <?php
                                        $completion = 0;
                                        $total_fields = 7;
                                        if (!empty($company['company_name'])) $completion++;
                                        if (!empty($company['contact_number'])) $completion++;
                                        if (!empty($company['contact_email'])) $completion++;
                                        if (!empty($company['location_address'])) $completion++;
                                        if (!empty($company['description'])) $completion++;
                                        if (!empty($company['company_logo'])) $completion++;
                                        if (!empty($company['business_permit'])) $completion++;
                                        
                                        $percentage = round(($completion / $total_fields) * 100);
                                        ?>
                                        <div class="progress mb-2">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                 aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo $completion; ?> of <?php echo $total_fields; ?> fields completed</small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Welcome!</strong><br>
                                        Complete your company profile to start posting jobs.
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <label class="form-label">Quick Actions:</label>
                                    <div class="d-grid gap-2">
                                        <a href="verify-document.php" class="btn btn-outline-primary">
                                            <i class="fas fa-file-circle-check me-2"></i>Verify Documents
                                        </a>
                                        <a href="#company-profile-form" class="btn btn-success">
                                            <i class="fas fa-pen-to-square me-2"></i>Update Profile
                                        </a>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <a href="dashboard.php" class="btn btn-outline-success">
                                        <i class="fas fa-chart-line me-2"></i>View Dashboard
                                    </a>
                                    <?php if ($company && $company['status'] === 'active'): ?>
                                        <a href="post-job.php" class="btn btn-success">
                                            <i class="fas fa-plus me-2"></i>Post New Job
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card cp-card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Help & Support</h5>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted">Need help with your profile?</p>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-check" style="color: #059669;" me-2"></i>Upload high-quality company logo</li>
                                    <li><i class="fas fa-check" style="color: #059669;" me-2"></i>Complete all required fields</li>
                                    <li><i class="fas fa-check" style="color: #059669;" me-2"></i>Provide accurate contact information</li>
                                    <li><i class="fas fa-check" style="color: #059669;" me-2"></i>Upload valid business permit</li>
                                </ul>
                                <a href="../contact.php" class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-envelope me-1"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(function(textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });

        // Preview uploaded images
        document.getElementById('company_logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You can add image preview functionality here
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
