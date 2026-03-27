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
    <link href="css/minimal.css" rel="stylesheet">
    <style>
        .profile-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }

        .profile-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .profile-header p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header i {
            width: 36px;
            height: 36px;
            background: #eff6ff;
            color: #3b82f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .section-header p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.375rem;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .document-card {
            background: #f9fafb;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }

        .document-card i {
            font-size: 2rem;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }

        .document-card img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .completion-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
        }

        .completion-percentage {
            text-align: center;
            margin-bottom: 1rem;
        }

        .completion-percentage .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .completion-percentage .label {
            color: #64748b;
            font-size: 0.875rem;
        }

        .completion-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .completion-list li {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.875rem;
            color: #374151;
        }

        .completion-list li.completed {
            color: #059669;
        }

        .completion-list li i {
            margin-right: 0.5rem;
        }

        .tips-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .tips-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tips-list li {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.875rem;
            color: #374151;
        }

        .tips-list li i {
            color: #10b981;
            margin-right: 0.5rem;
        }

        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

  
            <!-- Main Content -->
            <div class="employee-main-content">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1>My Profile</h1>
                            <p>Manage your personal information and documents</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <strong>Success!</strong> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Error!</strong> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Section Header -->
                                <div class="section-header">
                                    <i class="fas fa-user"></i>
                                    <div>
                                        <h4>Personal Information</h4>
                                        <p>Update your basic personal details</p>
                                    </div>
                                </div>
                                
                                <!-- Form Fields -->
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="employee_id" class="form-label">Employee ID</label>
                                            <input type="text" class="form-control" id="employee_id" value="<?php echo htmlspecialchars($profile['employee_id']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="profile_picture" class="form-label">ID Photo for Verification</label>
                                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png,.gif">
                                        <div class="form-text">Upload your ID photo for verification purposes</div>
                                        <?php if ($profile['profile_picture']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Current photo:</small>
                                                <div class="mt-1">
                                                    <img src="../<?php echo $profile['profile_picture']; ?>" alt="Profile Picture" class="photo-preview">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="sex" class="form-label">Sex *</label>
                                            <select class="form-select" id="sex" name="sex" required>
                                                <option value="">Select</option>
                                                <option value="Male" <?php echo ($profile['sex'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($profile['sex'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="civil_status" class="form-label">Civil Status *</label>
                                            <select class="form-select" id="civil_status" name="civil_status" required>
                                                <option value="">Select</option>
                                                <option value="Single" <?php echo ($profile['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="Married" <?php echo ($profile['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                                <option value="Divorced" <?php echo ($profile['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                                <option value="Widowed" <?php echo ($profile['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $profile['date_of_birth'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="place_of_birth" class="form-label">Place of Birth</label>
                                            <input type="text" class="form-control" id="place_of_birth" name="place_of_birth" value="<?php echo htmlspecialchars($profile['place_of_birth'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="contact_no" class="form-label">Contact Number *</label>
                                            <input type="text" class="form-control" id="contact_no" name="contact_no" value="<?php echo htmlspecialchars($profile['contact_no'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                <!-- Education Section -->
                                <div class="section-header mt-4">
                                    <i class="fas fa-graduation-cap"></i>
                                    <div>
                                        <h4>Education</h4>
                                        <p>Your educational background</p>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="highest_education" class="form-label">Educational Attainment *</label>
                                        <select class="form-select" id="highest_education" name="highest_education" required>
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

                                <!-- Skills Section -->
                                <div class="section-header mt-4">
                                    <i class="fas fa-tools"></i>
                                    <div>
                                        <h4>Skills & Certifications</h4>
                                        <p>Showcase your professional skills</p>
                                    </div>
                                </div>

                                <?php if (!empty($profile['skills'])): ?>
                                <div class="alert alert-info mb-3">
                                    <strong>Current Skill:</strong> <?php echo htmlspecialchars($profile['skills']); ?>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="skills" class="form-label">Skills</label>
                                        <select class="form-select" id="skills" name="skills">
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
                                            if (!$skillFound && !empty($currentSkill)): 
                                            ?>
                                                <option value="<?php echo htmlspecialchars($currentSkill); ?>" selected>
                                                    <?php echo htmlspecialchars($currentSkill); ?> (saved)
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="form-text">Select from list or add your own below</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="custom_skill" class="form-label">Or Add Custom Skill</label>
                                        <input type="text" class="form-control" id="custom_skill" name="custom_skill" placeholder="Enter your skill if not in list">
                                        <div class="form-text">Type your own skill (optional)</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="experience_level" class="form-label">Experience Level</label>
                                        <select class="form-select" id="experience_level" name="experience_level">
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

                                <!-- Documents Section -->
                                <div class="section-header mt-4">
                                    <i class="fas fa-file-alt"></i>
                                    <div>
                                        <h4>Resume / CV</h4>
                                        <p>Upload your professional documents</p>
                                    </div>
                                </div>

                                <!-- Display Uploaded Documents -->
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Document 1 (Resume/CV)</label>
                                        <div class="document-card">
                                            <?php 
                                            $doc1_exists = false;
                                            $doc1_url = '';
                                            if (!empty($profile['document1'])) {
                                                $doc1_relative_path = $profile['document1'];
                                                $doc1_url = '../' . htmlspecialchars($doc1_relative_path);
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
                                                    <img src="<?php echo $doc1_url; ?>" alt="Resume/CV">
                                                <?php elseif ($is_pdf1): ?>
                                                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file"></i>
                                                <?php endif; ?>
                                                
                                                <h6 class="mt-2 mb-1"><?php echo htmlspecialchars(basename($profile['document1'])); ?></h6>
                                                <small class="text-success"><i class="fas fa-check-circle me-1"></i>Uploaded</small>
                                                <div class="mt-2">
                                                    <a href="<?php echo $doc1_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                                    <a href="<?php echo $doc1_url; ?>" download class="btn btn-sm btn-outline-secondary">Download</a>
                                                </div>
                                            <?php else: ?>
                                                <i class="fas fa-file-upload"></i>
                                                <p class="mb-0 mt-2">No document uploaded</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Document 2 (Other)</label>
                                        <div class="document-card">
                                            <?php 
                                            $doc2_exists = false;
                                            $doc2_url = '';
                                            if (!empty($profile['document2'])) {
                                                $doc2_relative_path = $profile['document2'];
                                                $doc2_url = '../' . htmlspecialchars($doc2_relative_path);
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
                                                    <img src="<?php echo $doc2_url; ?>" alt="Other Document">
                                                <?php elseif ($is_pdf2): ?>
                                                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file"></i>
                                                <?php endif; ?>
                                                
                                                <h6 class="mt-2 mb-1"><?php echo htmlspecialchars(basename($profile['document2'])); ?></h6>
                                                <small class="text-success"><i class="fas fa-check-circle me-1"></i>Uploaded</small>
                                                <div class="mt-2">
                                                    <a href="<?php echo $doc2_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                                    <a href="<?php echo $doc2_url; ?>" download class="btn btn-sm btn-outline-secondary">Download</a>
                                                </div>
                                            <?php else: ?>
                                                <i class="fas fa-file-upload"></i>
                                                <p class="mb-0 mt-2">No document uploaded</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Update Documents Section -->
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>You can upload or replace your documents below. Leave blank to keep existing documents unchanged.
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="document1" class="form-label">
                                            <?php if ($profile['document1']): ?>Replace<?php else: ?>Upload<?php endif; ?> Document 1 (Resume/CV)
                                        </label>
                                        <input type="file" class="form-control" id="document1" name="document1" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                        <div class="form-text">JPG, PNG, GIF, or PDF</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="document2" class="form-label">
                                            <?php if ($profile['document2']): ?>Replace<?php else: ?>Upload<?php endif; ?> Document 2 (Other)
                                        </label>
                                        <input type="file" class="form-control" id="document2" name="document2" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                        <div class="form-text">JPG, PNG, GIF, or PDF</div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Profile Completion Card -->
                        <div class="completion-card">
                            <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Profile Completion</h5>
                            
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
                            
                            <div class="completion-percentage">
                                <span class="number"><?php echo round($completion_percentage); ?>%</span>
                                <span class="label">Complete</span>
                            </div>
                            
                            <div class="progress mb-3">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $completion_percentage; ?>%"></div>
                            </div>
                            
                            <p class="text-muted small mb-3">Complete your profile to increase your chances of getting hired.</p>
                            
                            <ul class="completion-list">
                                <li class="<?php echo !empty($profile['first_name']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo !empty($profile['first_name']) ? 'check-circle' : 'circle'; ?>"></i>
                                    <span>Personal Information</span>
                                </li>
                                <li class="<?php echo !empty($profile['address']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo !empty($profile['address']) ? 'check-circle' : 'circle'; ?>"></i>
                                    <span>Address</span>
                                </li>
                                <li class="<?php echo !empty($profile['highest_education']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo !empty($profile['highest_education']) ? 'check-circle' : 'circle'; ?>"></i>
                                    <span>Education</span>
                                </li>
                                <li class="<?php echo !empty($profile['skills']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo !empty($profile['skills']) ? 'check-circle' : 'circle'; ?>"></i>
                                    <span>Skills</span>
                                </li>
                                <li class="<?php echo !empty($profile['experience_level']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo !empty($profile['experience_level']) ? 'check-circle' : 'circle'; ?>"></i>
                                    <span>Experience</span>
                                </li>
                                <li class="<?php echo !empty($profile['document1']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo !empty($profile['document1']) ? 'check-circle' : 'circle'; ?>"></i>
                                    <span>Resume/CV</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Quick Tips Card -->
                        <div class="tips-card mt-3">
                            <h5 class="mb-3"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h5>
                            <ul class="tips-list">
                                <li><i class="fas fa-check-circle"></i><span>Keep your resume updated</span></li>
                                <li><i class="fas fa-check-circle"></i><span>Add a professional photo</span></li>
                                <li><i class="fas fa-check-circle"></i><span>Complete all profile sections</span></li>
                                <li><i class="fas fa-check-circle"></i><span>Highlight your skills</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
