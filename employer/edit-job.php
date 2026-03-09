<?php
include '../config.php';
requireRole('employer');

$message = '';
$error = '';

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    redirect('company-profile.php');
}

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    redirect('jobs.php');
}

// Get job details
$stmt = $pdo->prepare("SELECT * FROM job_postings WHERE id = ? AND company_id = ?");
$stmt->execute([$job_id, $company['id']]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['error'] = 'Job not found or you do not have permission to edit it.';
    redirect('jobs.php');
}

// Get job categories
$categories = $pdo->query("SELECT * FROM job_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $requirements = sanitizeInput($_POST['requirements']);
    $categoryId = (int)$_POST['category_id'];
    $location = sanitizeInput($_POST['location']);
    $salaryRange = sanitizeInput($_POST['salary_range']);
    $employmentType = sanitizeInput($_POST['employment_type']);
    $jobType = sanitizeInput($_POST['job_type']);
    $experienceLevel = sanitizeInput($_POST['experience_level']);
    $educationRequirement = sanitizeInput($_POST['education_requirement']);
    $employeesRequired = sanitizeInput($_POST['employees_required']);
    $deadline = !empty($_POST['deadline']) ? sanitizeInput($_POST['deadline']) : null;
    
    // Validation
    if (empty($title) || empty($description) || empty($location) || empty($employmentType) || empty($jobType) || empty($experienceLevel)) {
        $error = 'Please fill in all required fields.';
    } elseif ($categoryId <= 0) {
        $error = 'Please select a valid job category.';
    } else {
        try {
            // Update job
            $sql = "UPDATE job_postings SET 
                    title = ?, description = ?, requirements = ?, category_id = ?, 
                    location = ?, salary_range = ?, employment_type = ?, job_type = ?, 
                    experience_level = ?, education_requirement = ?, employees_required = ?, deadline = ?, updated_at = NOW()
                    WHERE id = ? AND company_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                $title, $description, $requirements, $categoryId, 
                $location, $salaryRange, $employmentType, $jobType, 
                $experienceLevel, $educationRequirement, $employeesRequired, $deadline, $job_id, $company['id']
            ]);
            
            if ($success) {
                $_SESSION['message'] = 'Job updated successfully!';
                redirect('jobs.php');
            } else {
                $error = 'Failed to update job. Please try again.';
            }
        } catch(PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-edit me-2"></i>Edit Job</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="jobs.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Jobs
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Job Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase me-2"></i>Job Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Job Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo $job['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($job['location']); ?>" 
                                           placeholder="e.g., Manila, Philippines" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="salary_range" class="form-label">Salary Range</label>
                                    <input type="text" class="form-control" id="salary_range" name="salary_range" 
                                           value="<?php echo htmlspecialchars($job['salary_range']); ?>" 
                                           placeholder="e.g., ₱25,000 - ₱35,000">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="employment_type" class="form-label">Work Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="employment_type" name="employment_type" required>
                                        <option value="">Select Work Type</option>
                                        <option value="Onsite" <?php echo $job['employment_type'] == 'Onsite' ? 'selected' : ''; ?>>Onsite</option>
                                        <option value="Work from Home" <?php echo $job['employment_type'] == 'Work from Home' ? 'selected' : ''; ?>>Work from Home</option>
                                        <option value="Hybrid" <?php echo $job['employment_type'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="job_type" class="form-label">Job Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="job_type" name="job_type" required>
                                        <option value="">Select Job Type</option>
                                        <option value="Full Time" <?php echo ($job['job_type'] ?? '') == 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                                        <option value="Part Time" <?php echo ($job['job_type'] ?? '') == 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                                        <option value="Freelance" <?php echo ($job['job_type'] ?? '') == 'Freelance' ? 'selected' : ''; ?>>Freelance</option>
                                        <option value="Internship" <?php echo ($job['job_type'] ?? '') == 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                        <option value="Contract-Based" <?php echo ($job['job_type'] ?? '') == 'Contract-Based' ? 'selected' : ''; ?>>Contract-Based</option>
                                        <option value="Temporary" <?php echo ($job['job_type'] ?? '') == 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                                        <option value="Work From Home" <?php echo ($job['job_type'] ?? '') == 'Work From Home' ? 'selected' : ''; ?>>Work From Home</option>
                                        <option value="On-Site" <?php echo ($job['job_type'] ?? '') == 'On-Site' ? 'selected' : ''; ?>>On-Site</option>
                                        <option value="Hybrid" <?php echo ($job['job_type'] ?? '') == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                        <option value="Seasonal" <?php echo ($job['job_type'] ?? '') == 'Seasonal' ? 'selected' : ''; ?>>Seasonal</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="experience_level" class="form-label">Experience Required <span class="text-danger">*</span></label>
                                    <select class="form-select" id="experience_level" name="experience_level" required>
                                        <option value="">Select Experience</option>
                                        <option value="0 to 1 year" <?php echo $job['experience_level'] == '0 to 1 year' ? 'selected' : ''; ?>>0 to 1 year</option>
                                        <option value="1 to 2 years" <?php echo $job['experience_level'] == '1 to 2 years' ? 'selected' : ''; ?>>1 to 2 years</option>
                                        <option value="2 to 5 years" <?php echo $job['experience_level'] == '2 to 5 years' ? 'selected' : ''; ?>>2 to 5 years</option>
                                        <option value="5 to 10 years" <?php echo $job['experience_level'] == '5 to 10 years' ? 'selected' : ''; ?>>5 to 10 years</option>
                                        <option value="10+ years" <?php echo $job['experience_level'] == '10+ years' ? 'selected' : ''; ?>>10+ years</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="employees_required" class="form-label">Number of Employees Required</label>
                                    <select class="form-select" id="employees_required" name="employees_required">
                                        <option value="">Select Number</option>
                                        <option value="1" <?php echo $job['employees_required'] == '1' ? 'selected' : ''; ?>>1 Employee</option>
                                        <option value="2-5" <?php echo $job['employees_required'] == '2-5' ? 'selected' : ''; ?>>2-5 Employees</option>
                                        <option value="6-10" <?php echo $job['employees_required'] == '6-10' ? 'selected' : ''; ?>>6-10 Employees</option>
                                        <option value="11-20" <?php echo $job['employees_required'] == '11-20' ? 'selected' : ''; ?>>11-20 Employees</option>
                                        <option value="21-50" <?php echo $job['employees_required'] == '21-50' ? 'selected' : ''; ?>>21-50 Employees</option>
                                        <option value="50+" <?php echo $job['employees_required'] == '50+' ? 'selected' : ''; ?>>50+ Employees</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="education_requirement" class="form-label">Education Requirement</label>
                                <select class="form-select" id="education_requirement" name="education_requirement">
                                    <option value="">Select Education Level (Optional)</option>
                                    <option value="Elementary" <?php echo ($job['education_requirement'] ?? '') === 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                    <option value="Junior High School" <?php echo ($job['education_requirement'] ?? '') === 'Junior High School' ? 'selected' : ''; ?>>Junior High School</option>
                                    <option value="Senior High School" <?php echo ($job['education_requirement'] ?? '') === 'Senior High School' ? 'selected' : ''; ?>>Senior High School</option>
                                    <option value="Junior College" <?php echo ($job['education_requirement'] ?? '') === 'Junior College' ? 'selected' : ''; ?>>Junior College</option>
                                    <option value="Graduate Studies" <?php echo ($job['education_requirement'] ?? '') === 'Graduate Studies' ? 'selected' : ''; ?>>Graduate Studies</option>
                                    <option value="Post Graduate" <?php echo ($job['education_requirement'] ?? '') === 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                    <option value="Senior College" <?php echo ($job['education_requirement'] ?? '') === 'Senior College' ? 'selected' : ''; ?>>Senior College</option>
                                    <option value="College Graduate" <?php echo ($job['education_requirement'] ?? '') === 'College Graduate' ? 'selected' : ''; ?>>College Graduate</option>
                                    <option value="N/A" <?php echo ($job['education_requirement'] ?? '') === 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="deadline" class="form-label">Application Deadline</label>
                                <input type="date" class="form-control" id="deadline" name="deadline" 
                                       value="<?php echo isset($job['deadline']) ? $job['deadline'] : ''; ?>" min="<?php echo date('Y-m-d'); ?>">
                                <div class="form-text">Leave empty for no deadline</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Job Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                <div class="form-text">Describe the job responsibilities, duties, and what the role entails.</div>
                            </div>

                            <div class="mb-3">
                                <label for="requirements" class="form-label">Requirements</label>
                                <textarea class="form-control" id="requirements" name="requirements" rows="5"><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                <div class="form-text">List the qualifications, skills, and experience required for this position.</div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="jobs.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Job
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Job Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="me-2">Current Status:</span>
                            <?php
                            $statusClass = '';
                            $statusIcon = '';
                            switch($job['status']) {
                                case 'active':
                                    $statusClass = 'bg-success text-white';
                                    $statusIcon = 'check';
                                    break;
                                case 'pending':
                                    $statusClass = 'bg-warning text-dark';
                                    $statusIcon = 'clock';
                                    break;
                                case 'expired':
                                    $statusClass = 'bg-danger text-white';
                                    $statusIcon = 'times';
                                    break;
                                case 'rejected':
                                    $statusClass = 'bg-dark';
                                    $statusIcon = 'ban';
                                    break;
                            }
                            ?>
                            <span class="badge <?php echo $statusClass; ?>">
                                <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                <?php echo ucfirst($job['status']); ?>
                            </span>
                        </div>
                        
                        <div class="small text-muted">
                            <p><strong>Posted:</strong> <?php echo date('M j, Y g:i A', strtotime($job['posted_date'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo isset($job['updated_at']) && $job['updated_at'] ? date('M j, Y g:i A', strtotime($job['updated_at'])) : 'Never'; ?></p>
                            <?php if (!empty($job['deadline'])): ?>
                                <p><strong>Deadline:</strong> <?php echo date('M j, Y', strtotime($job['deadline'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Job Statistics</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get job statistics
                        $stmt = $pdo->prepare("SELECT COUNT(*) as total_applications,
                                              SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                                              SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_applications,
                                              SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications
                                              FROM job_applications WHERE job_id = ?");
                        $stmt->execute([$job_id]);
                        $jobStats = $stmt->fetch();
                        ?>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="text-primary"><?php echo $jobStats['total_applications']; ?></h5>
                                <small>Total Applications</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-warning"><?php echo $jobStats['pending_applications']; ?></h5>
                                <small>Pending Review</small>
                            </div>
                        </div>
                        
                        <?php if ($jobStats['total_applications'] > 0): ?>
                            <div class="mt-3">
                                <a href="applications.php?job_id=<?php echo $job_id; ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-users me-1"></i>View Applications
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

