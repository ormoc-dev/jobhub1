<?php
include '../config.php';
requireRole('employee');

$message = '';
$error = '';

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.*, u.email FROM employee_profiles ep 
                       JOIN users u ON ep.user_id = u.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $middleName = sanitizeInput($_POST['middle_name']);
    $address = sanitizeInput($_POST['address']);
    $sex = sanitizeInput($_POST['sex']);
    $dateOfBirth = sanitizeInput($_POST['date_of_birth']);
    $placeOfBirth = sanitizeInput($_POST['place_of_birth']);
    $contactNo = sanitizeInput($_POST['contact_no']);
    $civilStatus = sanitizeInput($_POST['civil_status']);
    $position = sanitizeInput($_POST['position'] ?? '');
    $hiredDate = !empty($_POST['hired_date']) ? sanitizeInput($_POST['hired_date']) : null;
    $highestEducation = sanitizeInput($_POST['highest_education']);
    $skills = sanitizeInput($_POST['skills'] ?? '');
    $customSkill = sanitizeInput($_POST['custom_skill'] ?? '');
    // Use custom skill if provided, otherwise use selected skill
    if (!empty($customSkill)) {
        $skills = $customSkill;
    }
    $experienceLevel = sanitizeInput($_POST['experience_level']);
    
    try {
        $pdo->beginTransaction();
        
        // Handle file uploads
        $document1 = $profile['document1'] ?? '';
        $document2 = $profile['document2'] ?? '';
        $profilePicture = $profile['profile_picture'] ?? '';
        
        if (isset($_FILES['document1']) && $_FILES['document1']['error'] === UPLOAD_ERR_OK) {
            try {
                $newDoc1 = uploadFile($_FILES['document1'], 'employees');
                if ($newDoc1) {
                    $document1 = $newDoc1;
                } else {
                    throw new Exception('Failed to upload document 1. Please try again.');
                }
            } catch (Exception $e) {
                throw new Exception('Error uploading document 1: ' . $e->getMessage());
            }
        } elseif (isset($_FILES['document1']) && $_FILES['document1']['error'] === UPLOAD_ERR_NO_FILE) {
            // Keep existing document if no new file is selected
            // $document1 already set above
        }
        
        if (isset($_FILES['document2']) && $_FILES['document2']['error'] === UPLOAD_ERR_OK) {
            try {
                $newDoc2 = uploadFile($_FILES['document2'], 'employees');
                if ($newDoc2) {
                    $document2 = $newDoc2;
                } else {
                    throw new Exception('Failed to upload document 2. Please try again.');
                }
            } catch (Exception $e) {
                throw new Exception('Error uploading document 2: ' . $e->getMessage());
            }
        } elseif (isset($_FILES['document2']) && $_FILES['document2']['error'] === UPLOAD_ERR_NO_FILE) {
            // Keep existing document if no new file is selected
            // $document2 already set above
        }
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $newProfilePic = uploadFile($_FILES['profile_picture'], 'employees');
            if ($newProfilePic) {
                $profilePicture = $newProfilePic;
            } else {
                throw new Exception('Failed to upload profile picture. Please try again.');
            }
        } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception('Error uploading profile picture. Error code: ' . $_FILES['profile_picture']['error']);
        }
        
        // Update profile
        $stmt = $pdo->prepare("UPDATE employee_profiles SET 
                              first_name = ?, last_name = ?, middle_name = ?, address = ?, 
                              sex = ?, date_of_birth = ?, place_of_birth = ?, contact_no = ?, 
                              civil_status = ?, highest_education = ?, 
                              skills = ?, experience_level = ?, document1 = ?, document2 = ?, 
                              profile_picture = ?
                              WHERE user_id = ?");
        
        // Convert empty strings to NULL only if truly empty (not just keeping existing values)
        // This ensures we don't overwrite existing documents with NULL when not uploading new ones
        
        $stmt->execute([$firstName, $lastName, $middleName, $address, $sex, $dateOfBirth, 
                       $placeOfBirth, $contactNo, $civilStatus, 
                       $highestEducation, $skills, $experienceLevel, $document1, $document2, 
                       $profilePicture, $_SESSION['user_id']]);
        
        $pdo->commit();
        $message = 'Profile updated successfully!';
        
        // Refresh profile data
        $stmt = $pdo->prepare("SELECT ep.*, u.email FROM employee_profiles ep 
                               JOIN users u ON ep.user_id = u.id 
                               WHERE ep.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

  
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-0 profile-page modern-profile">
                <!-- Modern Profile Header -->
                <div class="profile-header-modern">
                    <div class="profile-header-content">
                        <div class="profile-header-text">
                            <div class="profile-badge">
                                <i class="fas fa-user-circle me-2"></i>Profile Management
                            </div>
                            <h1 class="profile-title-modern">
                                <span class="profile-title-main">My Profile</span>
                                <span class="profile-title-sub">Manage your personal information and documents</span>
                            </h1>
                        </div>
                        <a href="dashboard.php" class="btn btn-back-modern">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success-modern alert-dismissible fade show">
                        <div class="alert-icon-wrapper">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert-content-modern">
                            <strong>Success!</strong> <?php echo $message; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger-modern alert-dismissible fade show">
                        <div class="alert-icon-wrapper">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="alert-content-modern">
                            <strong>Error!</strong> <?php echo $error; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row profile-grid-modern">
                    <div class="col-lg-8">
                        <div class="form-container-modern profile-form-card-modern">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Section Header -->
                                <div class="section-header-modern">
                                    <div class="section-icon-wrapper">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="section-header-text">
                                        <h4 class="section-title-modern">Personal Information</h4>
                                        <p class="section-subtitle-modern">Update your basic personal details</p>
                                    </div>
                                </div>
                                
                                <!-- Form Fields -->
                                <div class="form-fields-modern">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="employee_id" class="form-label-modern">
                                                <i class="fas fa-id-badge me-2"></i>Employee ID
                                            </label>
                                            <input type="text" class="form-control-modern" id="employee_id" value="<?php echo htmlspecialchars($profile['employee_id']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label for="email" class="form-label-modern">
                                                <i class="fas fa-envelope me-2"></i>Email Address
                                            </label>
                                            <input type="email" class="form-control-modern" id="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-4">
                                            <label for="first_name" class="form-label-modern">
                                                First Name <span class="required-star">*</span>
                                            </label>
                                            <input type="text" class="form-control-modern" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" autocomplete="given-name" required>
                                        </div>
                                        <div class="col-md-4 mb-4">
                                            <label for="last_name" class="form-label-modern">
                                                Last Name <span class="required-star">*</span>
                                            </label>
                                            <input type="text" class="form-control-modern" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" autocomplete="family-name" required>
                                        </div>
                                        <div class="col-md-4 mb-4">
                                            <label for="middle_name" class="form-label-modern">Middle Name</label>
                                            <input type="text" class="form-control-modern" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" autocomplete="additional-name">
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="address" class="form-label-modern">
                                            <i class="fas fa-map-marker-alt me-2"></i>Address
                                        </label>
                                        <textarea class="form-control-modern" id="address" name="address" rows="3" autocomplete="street-address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="profile_picture" class="form-label-modern">
                                            <i class="fas fa-camera me-2"></i>ID Photo for Verification
                                        </label>
                                        <div class="file-upload-modern">
                                            <input type="file" class="form-control-modern" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png,.gif">
                                            <div class="form-text-modern">
                                                <i class="fas fa-info-circle me-1"></i>Upload your ID photo for verification purposes
                                            </div>
                                        </div>
                                        <?php if ($profile['profile_picture']): ?>
                                            <div class="current-photo-modern">
                                                <small class="current-photo-label">Current photo:</small>
                                                <div class="photo-preview-modern">
                                                    <img src="../<?php echo $profile['profile_picture']; ?>" alt="Profile Picture" class="photo-thumbnail-modern">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="sex" class="form-label-modern">
                                                Sex <span class="required-star">*</span>
                                            </label>
                                            <select class="form-control-modern form-select-modern" id="sex" name="sex" required>
                                                <option value="">Select</option>
                                                <option value="Male" <?php echo ($profile['sex'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($profile['sex'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label for="civil_status" class="form-label-modern">
                                                Civil Status <span class="required-star">*</span>
                                            </label>
                                            <select class="form-control-modern form-select-modern" id="civil_status" name="civil_status" required>
                                                <option value="">Select</option>
                                                <option value="Single" <?php echo ($profile['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="Married" <?php echo ($profile['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                                <option value="Divorced" <?php echo ($profile['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                                <option value="Widowed" <?php echo ($profile['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="date_of_birth" class="form-label-modern">
                                                <i class="fas fa-calendar-alt me-2"></i>Date of Birth <span class="required-star">*</span>
                                            </label>
                                            <input type="date" class="form-control-modern" id="date_of_birth" name="date_of_birth" value="<?php echo $profile['date_of_birth'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label for="place_of_birth" class="form-label-modern">
                                                <i class="fas fa-map-pin me-2"></i>Place of Birth
                                            </label>
                                            <input type="text" class="form-control-modern" id="place_of_birth" name="place_of_birth" value="<?php echo htmlspecialchars($profile['place_of_birth'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="contact_no" class="form-label-modern">
                                                <i class="fas fa-phone me-2"></i>Contact Number <span class="required-star">*</span>
                                            </label>
                                            <input type="text" class="form-control-modern" id="contact_no" name="contact_no" value="<?php echo htmlspecialchars($profile['contact_no'] ?? ''); ?>" autocomplete="tel" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Education Section -->
                                <div class="section-header-modern mt-5">
                                    <div class="section-icon-wrapper education-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div class="section-header-text">
                                        <h4 class="section-title-modern">Education</h4>
                                        <p class="section-subtitle-modern">Your educational background</p>
                                    </div>
                                </div>

                                <div class="form-fields-modern">
                                    <div class="row">
                                        <div class="col-md-12 mb-4">
                                            <label for="highest_education" class="form-label-modern">
                                                <i class="fas fa-graduation-cap me-2"></i>Educational Attainment <span class="required-star">*</span>
                                            </label>
                                            <select class="form-control-modern form-select-modern" id="highest_education" name="highest_education" required>
                                                <option value="">Select Educational Attainment</option>
                                                <option value="Elementary" <?php echo ($profile['highest_education'] ?? '') === 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                                <option value="Junior High School" <?php echo ($profile['highest_education'] ?? '') === 'Junior High School' ? 'selected' : ''; ?>>Junior High School</option>
                                                <option value="Senior High School" <?php echo ($profile['highest_education'] ?? '') === 'Senior High School' ? 'selected' : ''; ?>>Senior High School</option>
                                                <option value="Junior College" <?php echo ($profile['highest_education'] ?? '') === 'Junior College' ? 'selected' : ''; ?>>Junior College</option>
                                                <option value="Graduate Studies" <?php echo ($profile['highest_education'] ?? '') === 'Graduate Studies' ? 'selected' : ''; ?>>Graduate Studies</option>
                                                <option value="Post Graduate" <?php echo ($profile['highest_education'] ?? '') === 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                                <option value="Senior College" <?php echo ($profile['highest_education'] ?? '') === 'Senior College' ? 'selected' : ''; ?>>Senior College</option>
                                                <option value="College Graduate" <?php echo ($profile['highest_education'] ?? '') === 'College Graduate' ? 'selected' : ''; ?>>College Graduate</option>
                                                <option value="N/A" <?php echo ($profile['highest_education'] ?? '') === 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Skills Section -->
                                <div class="section-header-modern mt-5">
                                    <div class="section-icon-wrapper skills-icon">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                    <div class="section-header-text">
                                        <h4 class="section-title-modern">Skills & Certifications</h4>
                                        <p class="section-subtitle-modern">Showcase your professional skills</p>
                                    </div>
                                </div>

                                <?php if (!empty($profile['skills'])): ?>
                                <div class="alert alert-info-modern mb-4">
                                    <div class="alert-icon-wrapper">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="alert-content-modern">
                                        <strong>Current Skill:</strong> <?php echo htmlspecialchars($profile['skills']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="form-fields-modern">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="skills" class="form-label-modern">
                                                <i class="fas fa-star me-2"></i>Skills
                                            </label>
                                            <select class="form-control-modern form-select-modern" id="skills" name="skills">
                                                <option value="">Select one skill</option>
                                                <?php 
                                                $allSkills = getAllUniqueSkills();
                                                $currentSkill = trim($profile['skills'] ?? '');
                                                $skillFound = false;
                                                foreach ($allSkills as $skill): 
                                                    $skill = trim($skill);
                                                    $isSelected = ($currentSkill === $skill);
                                                    if ($isSelected) $skillFound = true;
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($skill); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($skill); ?>
                                                    </option>
                                                <?php endforeach; 
                                                // If current skill is not in the list but exists, add it as an option
                                                if (!$skillFound && !empty($currentSkill)): 
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($currentSkill); ?>" selected>
                                                        <?php echo htmlspecialchars($currentSkill); ?> (saved)
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                            <div class="form-text-modern">
                                                <i class="fas fa-info-circle me-1"></i>Select from list or add your own below
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label for="custom_skill" class="form-label-modern">
                                                <i class="fas fa-plus-circle me-2"></i>Or Add Custom Skill
                                            </label>
                                            <input type="text" class="form-control-modern" id="custom_skill" name="custom_skill" value="" placeholder="Enter your skill if not in list">
                                            <div class="form-text-modern">
                                                <i class="fas fa-lightbulb me-1"></i>Type your own skill (optional)
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="experience_level" class="form-label-modern">
                                                <i class="fas fa-briefcase me-2"></i>Experience Level
                                            </label>
                                            <select class="form-control-modern form-select-modern" id="experience_level" name="experience_level">
                                                <option value="">Select Experience Level</option>
                                                <option value="No Experience" <?php echo ($profile['experience_level'] ?? '') === 'No Experience' ? 'selected' : ''; ?>>No Experience</option>
                                                <option value="0-1 years" <?php echo ($profile['experience_level'] ?? '') === '0-1 years' ? 'selected' : ''; ?>>0-1 years</option>
                                                <option value="1-2 years" <?php echo ($profile['experience_level'] ?? '') === '1-2 years' ? 'selected' : ''; ?>>1-2 years</option>
                                                <option value="2-5 years" <?php echo ($profile['experience_level'] ?? '') === '2-5 years' ? 'selected' : ''; ?>>2-5 years</option>
                                                <option value="5-10 years" <?php echo ($profile['experience_level'] ?? '') === '5-10 years' ? 'selected' : ''; ?>>5-10 years</option>
                                                <option value="10+ years" <?php echo ($profile['experience_level'] ?? '') === '10+ years' ? 'selected' : ''; ?>>10+ years</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Documents Section -->
                                <div class="section-header-modern mt-5">
                                    <div class="section-icon-wrapper documents-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="section-header-text">
                                        <h4 class="section-title-modern">Resume / CV</h4>
                                        <p class="section-subtitle-modern">Upload your professional documents</p>
                                    </div>
                                </div>

                                <!-- Display Uploaded Documents -->
                                <div class="form-fields-modern">
                                    <div class="row mb-4">
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label-modern">
                                                <i class="fas fa-file-pdf me-2"></i>Document 1 (Resume/CV)
                                            </label>
                                            <div class="document-card-modern">
                                                <div class="document-card-body-modern">
                                                <?php 
                                                // Check if document exists
                                                $doc1_exists = false;
                                                $doc1_url = '';
                                                if (!empty($profile['document1'])) {
                                                    // Path is now relative: uploads/employees/filename.jpg
                                                    $doc1_relative_path = $profile['document1'];
                                                    $doc1_url = '../' . htmlspecialchars($doc1_relative_path);
                                                    
                                                    // Check if file exists on server
                                                    if (file_exists(__DIR__ . '/../' . $doc1_relative_path)) {
                                                        $doc1_exists = true;
                                                    }
                                                }
                                                
                                                if ($doc1_exists): 
                                                    $doc1_ext = strtolower(pathinfo($profile['document1'], PATHINFO_EXTENSION));
                                                    $is_pdf1 = ($doc1_ext === 'pdf');
                                                    $is_image1 = in_array($doc1_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                                ?>
                                                    <?php if ($is_image1): ?>
                                                        <img src="<?php echo $doc1_url; ?>" alt="Resume/CV" class="document-preview-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                        <div style="display:none;"><i class="fas fa-file-image document-icon-large"></i><p class="document-empty-text">Image not available</p></div>
                                                    <?php elseif ($is_pdf1): ?>
                                                        <i class="fas fa-file-pdf document-icon-large document-pdf-icon"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file document-icon-large"></i>
                                                    <?php endif; ?>
                                                    
                                                    <h6 class="document-title-modern"><?php echo htmlspecialchars(basename($profile['document1'])); ?></h6>
                                                    <small class="document-status-modern">
                                                        <i class="fas fa-check-circle me-1"></i>Document uploaded
                                                    </small>
                                                    <div class="document-actions-modern">
                                                        <a href="<?php echo $doc1_url; ?>" target="_blank" class="btn btn-view-modern">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                        <a href="<?php echo $doc1_url; ?>" download class="btn btn-download-modern">
                                                            <i class="fas fa-download me-1"></i>Download
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <i class="fas fa-file-upload document-empty-icon"></i>
                                                    <p class="document-empty-text">No document uploaded</p>
                                                    <p class="document-empty-subtext">Upload during registration or update below</p>
                                                <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label class="form-label-modern">
                                                <i class="fas fa-file-alt me-2"></i>Document 2 (Other)
                                            </label>
                                            <div class="document-card-modern">
                                                <div class="document-card-body-modern">
                                                <?php 
                                                // Check if document exists
                                                $doc2_exists = false;
                                                $doc2_url = '';
                                                if (!empty($profile['document2'])) {
                                                    // Path is now relative: uploads/employees/filename.jpg
                                                    $doc2_relative_path = $profile['document2'];
                                                    $doc2_url = '../' . htmlspecialchars($doc2_relative_path);
                                                    
                                                    // Check if file exists on server
                                                    if (file_exists(__DIR__ . '/../' . $doc2_relative_path)) {
                                                        $doc2_exists = true;
                                                    }
                                                }
                                                
                                                if ($doc2_exists): 
                                                    $doc2_ext = strtolower(pathinfo($profile['document2'], PATHINFO_EXTENSION));
                                                    $is_pdf2 = ($doc2_ext === 'pdf');
                                                    $is_image2 = in_array($doc2_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                                ?>
                                                    <?php if ($is_image2): ?>
                                                        <img src="<?php echo $doc2_url; ?>" alt="Other Document" class="document-preview-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                        <div style="display:none;"><i class="fas fa-file-image document-icon-large"></i><p class="document-empty-text">Image not available</p></div>
                                                    <?php elseif ($is_pdf2): ?>
                                                        <i class="fas fa-file-pdf document-icon-large document-pdf-icon"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file document-icon-large"></i>
                                                    <?php endif; ?>
                                                    
                                                    <h6 class="document-title-modern"><?php echo htmlspecialchars(basename($profile['document2'])); ?></h6>
                                                    <small class="document-status-modern">
                                                        <i class="fas fa-check-circle me-1"></i>Document uploaded
                                                    </small>
                                                    <div class="document-actions-modern">
                                                        <a href="<?php echo $doc2_url; ?>" target="_blank" class="btn btn-view-modern">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                        <a href="<?php echo $doc2_url; ?>" download class="btn btn-download-modern">
                                                            <i class="fas fa-download me-1"></i>Download
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <i class="fas fa-file-upload document-empty-icon"></i>
                                                    <p class="document-empty-text">No document uploaded</p>
                                                    <p class="document-empty-subtext">Upload during registration or update below</p>
                                                <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Update Documents Section -->
                                <div class="alert-info-modern">
                                    <div class="alert-info-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="alert-info-text">
                                        You can upload or replace your documents below. Leave blank to keep existing documents unchanged.
                                    </div>
                                </div>

                                <div class="form-fields-modern">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="document1" class="form-label-modern">
                                                <i class="fas fa-upload me-2"></i>
                                                <?php if ($profile['document1']): ?>
                                                    Replace Document 1 (Resume/CV)
                                                <?php else: ?>
                                                    Upload Document 1 (Resume/CV)
                                                <?php endif; ?>
                                            </label>
                                            <input type="file" class="form-control-modern" id="document1" name="document1" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                            <div class="form-text-modern">
                                                <?php if ($profile['document1']): ?>
                                                    <i class="fas fa-info-circle me-1"></i> Choose a new file to replace the existing one
                                                <?php else: ?>
                                                    <i class="fas fa-upload me-1"></i> Upload your resume or CV (JPG, PNG, GIF, PDF)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <label for="document2" class="form-label-modern">
                                                <i class="fas fa-upload me-2"></i>
                                                <?php if ($profile['document2']): ?>
                                                    Replace Document 2 (Other)
                                                <?php else: ?>
                                                    Upload Document 2 (Other)
                                                <?php endif; ?>
                                            </label>
                                            <input type="file" class="form-control-modern" id="document2" name="document2" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                            <div class="form-text-modern">
                                                <?php if ($profile['document2']): ?>
                                                    <i class="fas fa-info-circle me-1"></i> Choose a new file to replace the existing one
                                                <?php else: ?>
                                                    <i class="fas fa-upload me-1"></i> Upload additional documents (JPG, PNG, GIF, PDF)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-submit-modern">
                                    <button type="submit" class="btn btn-submit-modern">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Profile Completion Card -->
                        <div class="sidebar-card-modern completion-card-modern">
                            <div class="sidebar-card-header-modern">
                                <div class="sidebar-card-icon completion-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h5 class="sidebar-card-title-modern">Profile Completion</h5>
                            </div>
                            <div class="sidebar-card-body-modern">
                                <?php
                                $completed_fields = 0;
                                $total_fields = 12;
                                
                                if (!empty($profile['first_name'])) $completed_fields++;
                                if (!empty($profile['last_name'])) $completed_fields++;
                                if (!empty($profile['sex'])) $completed_fields++;
                                if (!empty($profile['date_of_birth'])) $completed_fields++;
                                if (!empty($profile['contact_no'])) $completed_fields++;
                                if (!empty($profile['civil_status'])) $completed_fields++;
                                if (!empty($profile['highest_education'])) $completed_fields++;
                                if (!empty($profile['address'])) $completed_fields++;
                                if (!empty($profile['skills'])) $completed_fields++;
                                if (!empty($profile['experience_level'])) $completed_fields++;
                                if (!empty($profile['document1'])) $completed_fields++;
                                if (!empty($profile['document2'])) $completed_fields++;
                                
                                $completion_percentage = ($completed_fields / $total_fields) * 100;
                                ?>
                                
                                <div class="completion-percentage-modern">
                                    <span class="percentage-number-modern"><?php echo round($completion_percentage); ?>%</span>
                                    <span class="percentage-label-modern">Complete</span>
                                </div>
                                
                                <div class="progress-modern mb-4">
                                    <div class="progress-bar-modern" role="progressbar" style="width: <?php echo $completion_percentage; ?>%">
                                    </div>
                                </div>
                                
                                <p class="completion-description-modern">Complete your profile to increase your chances of getting hired.</p>
                                
                                <ul class="completion-checklist-modern">
                                    <li class="completion-item-modern <?php echo !empty($profile['first_name']) ? 'completed' : ''; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['first_name']) ? 'check-circle' : 'circle'; ?> me-2"></i>
                                        <span>Personal Information</span>
                                    </li>
                                    <li class="completion-item-modern <?php echo !empty($profile['address']) ? 'completed' : ''; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['address']) ? 'check-circle' : 'circle'; ?> me-2"></i>
                                        <span>Address</span>
                                    </li>
                                    <li class="completion-item-modern <?php echo !empty($profile['highest_education']) ? 'completed' : ''; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['highest_education']) ? 'check-circle' : 'circle'; ?> me-2"></i>
                                        <span>Education</span>
                                    </li>
                                    <li class="completion-item-modern <?php echo !empty($profile['skills']) ? 'completed' : ''; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['skills']) ? 'check-circle' : 'circle'; ?> me-2"></i>
                                        <span>Skills</span>
                                    </li>
                                    <li class="completion-item-modern <?php echo !empty($profile['experience_level']) ? 'completed' : ''; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['experience_level']) ? 'check-circle' : 'circle'; ?> me-2"></i>
                                        <span>Experience</span>
                                    </li>
                                    <li class="completion-item-modern <?php echo !empty($profile['document1']) ? 'completed' : ''; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['document1']) ? 'check-circle' : 'circle'; ?> me-2"></i>
                                        <span>Resume/CV</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Quick Tips Card -->
                        <div class="sidebar-card-modern tips-card-modern mt-4">
                            <div class="sidebar-card-header-modern">
                                <div class="sidebar-card-icon tips-icon">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <h5 class="sidebar-card-title-modern">Quick Tips</h5>
                            </div>
                            <div class="sidebar-card-body-modern">
                                <ul class="tips-list-modern">
                                    <li class="tip-item-modern">
                                        <i class="fas fa-check-circle tip-icon"></i>
                                        <span>Keep your resume updated</span>
                                    </li>
                                    <li class="tip-item-modern">
                                        <i class="fas fa-check-circle tip-icon"></i>
                                        <span>Add a professional photo</span>
                                    </li>
                                    <li class="tip-item-modern">
                                        <i class="fas fa-check-circle tip-icon"></i>
                                        <span>Complete all profile sections</span>
                                    </li>
                                    <li class="tip-item-modern">
                                        <i class="fas fa-check-circle tip-icon"></i>
                                        <span>Highlight your skills</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
