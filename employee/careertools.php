<?php
include '../config.php';
requireRole('employee');

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.*, u.email, u.status FROM employee_profiles ep 
                       JOIN users u ON ep.user_id = u.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $pdo->prepare("INSERT INTO employee_profiles (user_id, employee_id) VALUES (?, ?)");
        $employeeId = 'EMP' . str_pad($_SESSION['user_id'], 6, '0', STR_PAD_LEFT);
        $stmt->execute([$_SESSION['user_id'], $employeeId]);
        redirect('profile.php');
    } else {
        session_destroy();
        $_SESSION = [];
        redirect('../login.php');
    }
}

$message = '';
$error = '';
$employee_id = $profile['id'];  

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle Resume Builder form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['full_name'])) {
    try {
        $full_name = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $location = sanitizeInput($_POST['location']);
        $summary = sanitizeInput($_POST['summary'] ?? '');
        $experience = json_encode($_POST['experience'] ?? []);
        $education = json_encode($_POST['education'] ?? []);
        $skills = sanitizeInput($_POST['skills'] ?? '');
        
        $resume_photo = '';
        try {
            $stmt = $pdo->prepare("SELECT photo FROM employee_resumes WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$employee_id]);
            $row = $stmt->fetch();
            if ($row && !empty($row['photo'])) $resume_photo = $row['photo'];
        } catch (PDOException $e) {}
        if ($resume_photo === '' && !empty($_SESSION['resume_data']['photo'])) {
            $resume_photo = $_SESSION['resume_data']['photo'];
        }
        if (isset($_FILES['resume_photo']) && $_FILES['resume_photo']['error'] === UPLOAD_ERR_OK) {
            try {
                $resume_photo = uploadFile($_FILES['resume_photo'], 'employees/resume_photos');
            } catch (Exception $e) {
                throw new Exception('Photo upload failed: ' . $e->getMessage());
            }
        }
        
        // Try to insert/update resume (table may not exist, so we'll catch the error)
        try {
            $stmt = $pdo->prepare("INSERT INTO employee_resumes (employee_id, full_name, email, phone, location, summary, experience, education, skills, photo, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE 
                                   full_name = VALUES(full_name), email = VALUES(email), phone = VALUES(phone), 
                                   location = VALUES(location), summary = VALUES(summary), experience = VALUES(experience), 
                                   education = VALUES(education), skills = VALUES(skills), photo = VALUES(photo), created_at = NOW()");
            $stmt->execute([$employee_id, $full_name, $email, $phone, $location, $summary, $experience, $education, $skills, $resume_photo ?: null]);
            $_SESSION['success_message'] = 'Resume saved successfully!';
        } catch (PDOException $e) {
            // Table doesn't exist, store in session instead
            $_SESSION['resume_data'] = [
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'location' => $location,
                'summary' => $summary,
                'experience' => $_POST['experience'] ?? [],
                'education' => $_POST['education'] ?? [],
                'skills' => $skills,
                'photo' => $resume_photo,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $_SESSION['success_message'] = 'Resume saved successfully! (Note: Database table not found, saved to session)';
        }
        redirect('careertools.php');
    } catch (Exception $e) {
        $error = 'Failed to save resume: ' . $e->getMessage();
    }
}

// Handle Career Assessment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['motivation'])) {
    try {
        $motivation = sanitizeInput($_POST['motivation']);
        $environment = sanitizeInput($_POST['environment']);
        $goals = json_encode($_POST['goals'] ?? []);
        $interest_tech = (int)($_POST['interest_tech'] ?? 3);
        $interest_business = (int)($_POST['interest_business'] ?? 3);
        $interest_creative = (int)($_POST['interest_creative'] ?? 3);
        
        // Determine career type based on answers
        $career_type = 'General Professional';
        if ($interest_tech >= 4) {
            $career_type = 'Technology Specialist';
        } elseif ($interest_business >= 4) {
            $career_type = 'Business Professional';
        } elseif ($interest_creative >= 4) {
            $career_type = 'Creative Professional';
        }
        
        $strengths = "Motivated by " . ucfirst($motivation) . ", prefers " . ucfirst($environment) . " environment";
        $recommendations = "Based on your assessment, consider focusing on " . strtolower($career_type) . " roles that align with your interests.";
        
        try {
            $stmt = $pdo->prepare("INSERT INTO career_assessments (employee_id, career_type, strengths, recommendations, assessment_data, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE 
                                   career_type = VALUES(career_type), strengths = VALUES(strengths), 
                                   recommendations = VALUES(recommendations), assessment_data = VALUES(assessment_data), created_at = NOW()");
            $assessment_data = json_encode([
                'motivation' => $motivation,
                'environment' => $environment,
                'goals' => $_POST['goals'] ?? [],
                'interests' => [
                    'tech' => $interest_tech,
                    'business' => $interest_business,
                    'creative' => $interest_creative
                ]
            ]);
            $stmt->execute([$employee_id, $career_type, $strengths, $recommendations, $assessment_data]);
            $_SESSION['success_message'] = 'Career assessment completed successfully!';
        } catch (PDOException $e) {
            $_SESSION['assessment_data'] = [
                'career_type' => $career_type,
                'strengths' => $strengths,
                'recommendations' => $recommendations,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $_SESSION['success_message'] = 'Career assessment completed! (Note: Database table not found, saved to session)';
        }
        redirect('careertools.php');
    } catch (Exception $e) {
        $error = 'Failed to save assessment: ' . $e->getMessage();
    }
}

// Handle Skill Gap Analysis form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['target_job'])) {
    try {
        $target_job = sanitizeInput($_POST['target_job']);
        $skills_data = json_encode($_POST['skills'] ?? []);
        
        // Generate recommendations based on skills
        $recommendations = [];
        if (isset($_POST['skills']['javascript']) && $_POST['skills']['javascript'] < 4) {
            $recommendations[] = 'Improve JavaScript skills through advanced courses and projects';
        }
        if (isset($_POST['skills']['project_management']) && $_POST['skills']['project_management'] < 3) {
            $recommendations[] = 'Take project management certification courses';
        }
        if (isset($_POST['skills']['data_analysis']) && $_POST['skills']['data_analysis'] < 3) {
            $recommendations[] = 'Practice data analysis with real-world projects';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Continue developing your skills and stay updated with industry trends';
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO skill_gap_analysis (employee_id, target_job, skills_data, recommendations, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE 
                                   target_job = VALUES(target_job), skills_data = VALUES(skills_data), 
                                   recommendations = VALUES(recommendations), created_at = NOW()");
            $stmt->execute([$employee_id, $target_job, $skills_data, json_encode($recommendations)]);
            $_SESSION['success_message'] = 'Skill gap analysis saved successfully!';
        } catch (PDOException $e) {
            $_SESSION['skill_gap_data'] = [
                'target_job' => $target_job,
                'skills' => $_POST['skills'] ?? [],
                'recommendations' => $recommendations,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $_SESSION['success_message'] = 'Skill gap analysis saved! (Note: Database table not found, saved to session)';
        }
        redirect('careertools.php');
    } catch (Exception $e) {
        $error = 'Failed to save skill gap analysis: ' . $e->getMessage();
    }
}

// Get saved resume data if exists (check both database and session)
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_resumes WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    $saved_resume = $stmt->fetch();
} catch (PDOException $e) {
    $saved_resume = $_SESSION['resume_data'] ?? null;
}

// Get career assessment results if exists
try {
    $stmt = $pdo->prepare("SELECT * FROM career_assessments WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    $assessment_result = $stmt->fetch();
} catch (PDOException $e) {
    $assessment_result = $_SESSION['assessment_data'] ?? null;
}

// Get skill gap analysis if exists
try {
    $stmt = $pdo->prepare("SELECT * FROM skill_gap_analysis WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    $skill_gap = $stmt->fetch();
    if ($skill_gap && isset($skill_gap['recommendations'])) {
        $skill_gap['recommendations'] = json_decode($skill_gap['recommendations'], true) ?? [];
    }
} catch (PDOException $e) {
    $skill_gap = $_SESSION['skill_gap_data'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Tools - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/minimal.css" rel="stylesheet">
    <style>
        .career-tool-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 2rem;
            background: #ffffff;
        }

        .career-tool-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        .tool-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0;
        }

        .tool-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .tool-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .tool-body {
            padding: 2rem;
        }

        .resume-builder-section {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }

        .career-assessment-section {
            background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
        }

        .skill-gap-section {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h5 {
            color: #4f46e5;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .btn-tool-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-tool-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.3);
            color: white;
        }

        .btn-tool-secondary {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-tool-secondary:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .preview-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .assessment-question {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .assessment-question h6 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .skill-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .skill-name {
            font-weight: 600;
            color: #1e293b;
        }

        .skill-level {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .badge-skill {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-expert {
            background: #10b981;
            color: white;
        }

        .badge-advanced {
            background: #3b82f6;
            color: white;
        }

        .badge-intermediate {
            background: #f59e0b;
            color: white;
        }

        .badge-beginner {
            background: #94a3b8;
            color: white;
        }

        .tool-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }

        .tool-icon i {
            font-size: 2rem;
            color: white;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-complete {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .resume-photo-wrap {
            min-height: 180px;
        }
        .resume-photo-preview {
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .resume-photo-img {
            max-width: 100%;
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        .resume-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Live Resume Preview Styles */
        .resume-preview-container {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 20px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.08);
        }
        .resume-preview {
            background: #ffffff;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .resume-header {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4f46e5;
            margin-bottom: 24px;
        }
        .resume-photo-area {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 3px solid #4f46e5;
        }
        .resume-photo-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resume-header-info {
            flex: 1;
        }
        .resume-header-info h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 8px 0;
        }
        .resume-contact {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 0.9rem;
            color: #475569;
        }
        .resume-contact span {
            display: flex;
            align-items: center;
        }
        .resume-contact i {
            color: #4f46e5;
        }
        .resume-section {
            margin-bottom: 24px;
        }
        .resume-section h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #4f46e5;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .resume-section h2 i {
            font-size: 1rem;
        }
        .resume-section p {
            color: #334155;
            line-height: 1.6;
            margin: 0;
        }
        .resume-entry {
            margin-bottom: 16px;
            padding-left: 12px;
            border-left: 3px solid #e2e8f0;
        }
        .resume-entry:hover {
            border-left-color: #4f46e5;
        }
        .resume-entry-title {
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }
        .resume-entry-subtitle {
            color: #4f46e5;
            font-size: 0.9rem;
        }
        .resume-entry-date {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        .resume-entry-desc {
            color: #475569;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .resume-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .resume-skill-tag {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        @media print {
            .resume-preview-container { background: none; padding: 0; box-shadow: none; }
            .resume-preview { box-shadow: none; padding: 20px; }
        }
    </style>
</head>
<body class="employee-layout employee-dashboard-ui">
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <div class="employee-main-content" style="flex: 1;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Career Tools</h1>
                    <p class="text-muted mb-0">Build your resume, assess your career, and identify skill gaps</p>
                </div>
            </div>

            <!-- Resume Builder Section -->
            <div class="career-tool-card">
                <div class="tool-header resume-builder-section">
                    <div class="tool-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3><i class="fas fa-file-alt me-2"></i>Resume Builder</h3>
                    <p>Create a professional resume that stands out to employers</p>
                </div>
                <div class="tool-body">
                    <?php if ($saved_resume): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Resume Saved!</strong> Last updated: <?php echo date('M d, Y', strtotime($saved_resume['created_at'])); ?>
                            </div>
                            <span class="status-badge status-complete">Complete</span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Get Started:</strong> Build your professional resume now
                            </div>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form id="resumeForm" method="POST" enctype="multipart/form-data">
                        <!-- Personal Information -->
                        <div class="form-section">
                            <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Resume Photo</label>
                                    <div class="resume-photo-wrap border rounded p-3 text-center bg-light">
                                        <div id="resumePhotoPreview" class="resume-photo-preview mb-2">
                                            <?php
                                            $photo_src = '';
                                            if (!empty($saved_resume['photo']) && file_exists('../' . $saved_resume['photo'])) {
                                                $photo_src = '../' . htmlspecialchars($saved_resume['photo']);
                                            } elseif (!empty($profile['profile_picture']) && file_exists('../' . $profile['profile_picture'])) {
                                                $photo_src = '../' . htmlspecialchars($profile['profile_picture']);
                                            }
                                            if ($photo_src): ?>
                                                <img src="<?php echo $photo_src; ?>" alt="Resume Photo" class="resume-photo-img">
                                            <?php else: ?>
                                                <div class="resume-photo-placeholder"><i class="fas fa-user fa-3x text-muted"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" class="form-control form-control-sm" id="resume_photo" name="resume_photo" accept=".jpg,.jpeg,.png,.gif,.webp">
                                        <small class="text-muted d-block mt-1">JPG, PNG, GIF. Max 10MB.</small>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" name="full_name" 
                                                   value="<?php echo htmlspecialchars($saved_resume['full_name'] ?? $profile['first_name'] . ' ' . $profile['last_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($saved_resume['email'] ?? $profile['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?php echo htmlspecialchars($saved_resume['phone'] ?? $profile['contact_no'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Location</label>
                                            <input type="text" class="form-control" name="location" 
                                                   value="<?php echo htmlspecialchars($saved_resume['location'] ?? $profile['address'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Professional Summary</label>
                                            <textarea class="form-control" name="summary" rows="4" 
                                                      placeholder="Write a brief summary of your professional background and key strengths..."><?php echo htmlspecialchars($saved_resume['summary'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Work Experience -->
                        <div class="form-section">
                            <h5><i class="fas fa-briefcase me-2"></i>Work Experience</h5>
                            <div id="experienceContainer">
                                <div class="experience-item border rounded p-3 mb-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Job Title</label>
                                            <input type="text" class="form-control" name="experience[0][title]" placeholder="e.g., Software Developer">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Company</label>
                                            <input type="text" class="form-control" name="experience[0][company]" placeholder="Company Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Start Date</label>
                                            <input type="month" class="form-control" name="experience[0][start_date]">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">End Date</label>
                                            <input type="month" class="form-control" name="experience[0][end_date]">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="experience[0][description]" rows="3" placeholder="Describe your responsibilities and achievements..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-tool-secondary" onclick="addExperience()">
                                <i class="fas fa-plus me-2"></i>Add Experience
                            </button>
                        </div>

                        <!-- Education -->
                        <div class="form-section">
                            <h5><i class="fas fa-graduation-cap me-2"></i>Education</h5>
                            <div id="educationContainer">
                                <div class="education-item border rounded p-3 mb-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Degree</label>
                                            <input type="text" class="form-control" name="education[0][degree]" 
                                                   placeholder="e.g., Bachelor of Science" 
                                                   value="<?php echo htmlspecialchars($profile['highest_education'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Institution</label>
                                            <input type="text" class="form-control" name="education[0][institution]" placeholder="University Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Graduation Year</label>
                                            <input type="text" class="form-control" name="education[0][year]" placeholder="e.g., 2020">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">GPA (Optional)</label>
                                            <input type="text" class="form-control" name="education[0][gpa]" placeholder="e.g., 3.8">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-tool-secondary" onclick="addEducation()">
                                <i class="fas fa-plus me-2"></i>Add Education
                            </button>
                        </div>

                        <!-- Skills -->
                        <div class="form-section">
                            <h5><i class="fas fa-tools me-2"></i>Skills</h5>
                            <div class="mb-3">
                                <label class="form-label">Add Skills (comma-separated)</label>
                                <input type="text" class="form-control" id="skillsInput" 
                                       placeholder="e.g., JavaScript, PHP, MySQL, Project Management">
                                <small class="text-muted">Press Enter or click Add to add skills</small>
                            </div>
                            <div id="skillsList" class="d-flex flex-wrap gap-2 mb-3"></div>
                            <input type="hidden" name="skills" id="skillsHidden">
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-tool-primary">
                                <i class="fas fa-save me-2"></i>Save Resume
                            </button>
                            <button type="button" class="btn btn-tool-secondary" onclick="printResume()">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <button type="button" class="btn btn-tool-secondary" onclick="downloadResume()">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </button>
                        </div>
                    </form>

                    <!-- Live Resume Preview -->
                    <div class="mt-4">
                        <h5 class="mb-3"><i class="fas fa-eye me-2 text-primary"></i>Live Resume Preview</h5>
                        <p class="text-muted small mb-3">Your resume updates automatically as you type</p>
                        <div id="resumePreviewContainer" class="resume-preview-container">
                            <div id="resumePreview" class="resume-preview">
                                <div class="resume-header">
                                    <div class="resume-photo-area" id="previewPhoto">
                                        <?php if ($photo_src): ?>
                                            <img src="<?php echo $photo_src; ?>" alt="Photo">
                                        <?php else: ?>
                                            <i class="fas fa-user fa-3x text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="resume-header-info">
                                        <h1 id="previewName">Your Name</h1>
                                        <div class="resume-contact">
                                            <span id="previewEmail"><i class="fas fa-envelope me-1"></i>email@example.com</span>
                                            <span id="previewPhone"><i class="fas fa-phone me-1"></i>+63 000 000 0000</span>
                                            <span id="previewLocation"><i class="fas fa-map-marker-alt me-1"></i>Location</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="resume-section" id="previewSummarySection" style="display:none;">
                                    <h2><i class="fas fa-user-circle me-2"></i>Professional Summary</h2>
                                    <p id="previewSummary"></p>
                                </div>

                                <div class="resume-section" id="previewExperienceSection" style="display:none;">
                                    <h2><i class="fas fa-briefcase me-2"></i>Work Experience</h2>
                                    <div id="previewExperience"></div>
                                </div>

                                <div class="resume-section" id="previewEducationSection" style="display:none;">
                                    <h2><i class="fas fa-graduation-cap me-2"></i>Education</h2>
                                    <div id="previewEducation"></div>
                                </div>

                                <div class="resume-section" id="previewSkillsSection" style="display:none;">
                                    <h2><i class="fas fa-tools me-2"></i>Skills</h2>
                                    <div id="previewSkills" class="resume-skills"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Career Assessment Section -->
            <div class="career-tool-card">
                <div class="tool-header career-assessment-section">
                    <div class="tool-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3><i class="fas fa-clipboard-check me-2"></i>Career Assessment</h3>
                    <p>Discover your career strengths and find the right path for you</p>
                </div>
                <div class="tool-body">
                    <?php if ($assessment_result): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Assessment Complete!</strong> Last taken: <?php echo date('M d, Y', strtotime($assessment_result['created_at'])); ?>
                            </div>
                            <span class="status-badge status-complete">Complete</span>
                        </div>
                        <div class="preview-section">
                            <h5>Your Career Profile</h5>
                            <p><strong>Career Type:</strong> <?php echo htmlspecialchars($assessment_result['career_type'] ?? 'Not specified'); ?></p>
                            <p><strong>Strengths:</strong> <?php echo htmlspecialchars($assessment_result['strengths'] ?? 'Not specified'); ?></p>
                            <p><strong>Recommendations:</strong> <?php echo htmlspecialchars($assessment_result['recommendations'] ?? 'Not specified'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Get Started:</strong> Take the assessment to discover your career path
                            </div>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                    <?php endif; ?>

                    <form id="assessmentForm" method="POST">
                        <div class="assessment-question">
                            <h6>1. What motivates you most in your work?</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="motivation" id="motivation1" value="creativity" required>
                                <label class="form-check-label" for="motivation1">Creative problem-solving and innovation</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="motivation" id="motivation2" value="leadership">
                                <label class="form-check-label" for="motivation2">Leading teams and making strategic decisions</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="motivation" id="motivation3" value="helping">
                                <label class="form-check-label" for="motivation3">Helping others and making a positive impact</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="motivation" id="motivation4" value="stability">
                                <label class="form-check-label" for="motivation4">Stability and predictable work environment</label>
                            </div>
                        </div>

                        <div class="assessment-question">
                            <h6>2. What type of work environment do you prefer?</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="environment" id="env1" value="fast-paced" required>
                                <label class="form-check-label" for="env1">Fast-paced and dynamic</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="environment" id="env2" value="collaborative">
                                <label class="form-check-label" for="env2">Collaborative team environment</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="environment" id="env3" value="independent">
                                <label class="form-check-label" for="env3">Independent and autonomous</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="environment" id="env4" value="structured">
                                <label class="form-check-label" for="env4">Structured and organized</label>
                            </div>
                        </div>

                        <div class="assessment-question">
                            <h6>3. What are your top career goals for the next 5 years?</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="goals[]" id="goal1" value="advancement">
                                <label class="form-check-label" for="goal1">Rapid career advancement</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="goals[]" id="goal2" value="expertise">
                                <label class="form-check-label" for="goal2">Become an expert in my field</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="goals[]" id="goal3" value="entrepreneurship">
                                <label class="form-check-label" for="goal3">Start my own business</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="goals[]" id="goal4" value="work-life">
                                <label class="form-check-label" for="goal4">Better work-life balance</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="goals[]" id="goal5" value="impact">
                                <label class="form-check-label" for="goal5">Make a meaningful impact</label>
                            </div>
                        </div>

                        <div class="assessment-question">
                            <h6>4. Rate your interest level in these areas (1-5 scale)</h6>
                            <div class="mb-3">
                                <label class="form-label">Technology & Innovation</label>
                                <input type="range" class="form-range" name="interest_tech" min="1" max="5" value="3">
                                <div class="d-flex justify-content-between">
                                    <small>Low</small>
                                    <small>High</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Business & Management</label>
                                <input type="range" class="form-range" name="interest_business" min="1" max="5" value="3">
                                <div class="d-flex justify-content-between">
                                    <small>Low</small>
                                    <small>High</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Creative & Design</label>
                                <input type="range" class="form-range" name="interest_creative" min="1" max="5" value="3">
                                <div class="d-flex justify-content-between">
                                    <small>Low</small>
                                    <small>High</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-tool-primary">
                            <i class="fas fa-check-circle me-2"></i>Complete Assessment
                        </button>
                    </form>
                </div>
            </div>

            <!-- Skill Gap Analysis Section -->
            <div class="career-tool-card">
                <div class="tool-header skill-gap-section">
                    <div class="tool-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3><i class="fas fa-chart-line me-2"></i>Skill Gap Analysis</h3>
                    <p>Identify skills you need to develop for your target roles</p>
                </div>
                <div class="tool-body">
                    <?php if ($skill_gap): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Analysis Complete!</strong> Last updated: <?php echo date('M d, Y', strtotime($skill_gap['created_at'])); ?>
                            </div>
                            <span class="status-badge status-complete">Complete</span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Get Started:</strong> Analyze your skills and identify gaps
                            </div>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                    <?php endif; ?>

                    <form id="skillGapForm" method="POST">
                        <div class="mb-4">
                            <label class="form-label"><strong>Target Job Title</strong></label>
                            <input type="text" class="form-control" name="target_job" 
                                   placeholder="e.g., Senior Software Engineer" 
                                   value="<?php echo htmlspecialchars($skill_gap['target_job'] ?? ''); ?>" required>
                            <small class="text-muted">Enter the job title you're aiming for</small>
                        </div>

                        <h5 class="mb-3">Rate Your Current Skills (1-5 scale)</h5>
                        
                        <div id="skillsAnalysisContainer">
                            <div class="skill-item">
                                <div>
                                    <div class="skill-name">JavaScript</div>
                                    <small class="text-muted">Programming Language</small>
                                </div>
                                <div class="skill-level">
                                    <input type="range" class="form-range" name="skills[javascript]" min="1" max="5" value="3" 
                                           oninput="updateSkillLevel(this, 'javascript')">
                                    <span class="badge badge-intermediate" id="badge-javascript">Intermediate</span>
                                </div>
                            </div>

                            <div class="skill-item">
                                <div>
                                    <div class="skill-name">Project Management</div>
                                    <small class="text-muted">Management Skill</small>
                                </div>
                                <div class="skill-level">
                                    <input type="range" class="form-range" name="skills[project_management]" min="1" max="5" value="2" 
                                           oninput="updateSkillLevel(this, 'project_management')">
                                    <span class="badge badge-beginner" id="badge-project_management">Beginner</span>
                                </div>
                            </div>

                            <div class="skill-item">
                                <div>
                                    <div class="skill-name">Communication</div>
                                    <small class="text-muted">Soft Skill</small>
                                </div>
                                <div class="skill-level">
                                    <input type="range" class="form-range" name="skills[communication]" min="1" max="5" value="4" 
                                           oninput="updateSkillLevel(this, 'communication')">
                                    <span class="badge badge-advanced" id="badge-communication">Advanced</span>
                                </div>
                            </div>

                            <div class="skill-item">
                                <div>
                                    <div class="skill-name">Data Analysis</div>
                                    <small class="text-muted">Technical Skill</small>
                                </div>
                                <div class="skill-level">
                                    <input type="range" class="form-range" name="skills[data_analysis]" min="1" max="5" value="2" 
                                           oninput="updateSkillLevel(this, 'data_analysis')">
                                    <span class="badge badge-beginner" id="badge-data_analysis">Beginner</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 mb-4">
                            <button type="button" class="btn btn-tool-secondary" onclick="addCustomSkill()">
                                <i class="fas fa-plus me-2"></i>Add Custom Skill
                            </button>
                        </div>

                        <div class="preview-section">
                            <h5>Recommended Learning Path</h5>
                            <p class="text-muted">Based on your target role, we recommend focusing on:</p>
                            <ul id="recommendationsList">
                                <?php if ($skill_gap && isset($skill_gap['recommendations']) && is_array($skill_gap['recommendations'])): ?>
                                    <?php foreach ($skill_gap['recommendations'] as $rec): ?>
                                        <li><?php echo htmlspecialchars($rec); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>Improve JavaScript skills through advanced courses</li>
                                    <li>Take project management certification</li>
                                    <li>Practice data analysis with real-world projects</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-tool-primary mt-3">
                            <i class="fas fa-save me-2"></i>Save Analysis
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let experienceCount = 1;
        let educationCount = 1;
        let skillsList = [];

        document.getElementById('resume_photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('resumePhotoPreview');
            const previewPhoto = document.getElementById('previewPhoto');
            if (!file || !file.type.startsWith('image/')) return;
            const r = new FileReader();
            r.onload = function() {
                preview.innerHTML = '<img src="' + r.result + '" alt="Preview" class="resume-photo-img">';
                previewPhoto.innerHTML = '<img src="' + r.result + '" alt="Photo">';
            };
            r.readAsDataURL(file);
        });

        function addExperience() {
            const container = document.getElementById('experienceContainer');
            const newItem = document.createElement('div');
            newItem.className = 'experience-item border rounded p-3 mb-3';
            newItem.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Experience ${experienceCount + 1}</strong>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Job Title</label>
                        <input type="text" class="form-control" name="experience[${experienceCount}][title]" placeholder="e.g., Software Developer">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Company</label>
                        <input type="text" class="form-control" name="experience[${experienceCount}][company]" placeholder="Company Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Date</label>
                        <input type="month" class="form-control" name="experience[${experienceCount}][start_date]">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date</label>
                        <input type="month" class="form-control" name="experience[${experienceCount}][end_date]">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="experience[${experienceCount}][description]" rows="3" placeholder="Describe your responsibilities and achievements..."></textarea>
                    </div>
                </div>
            `;
            container.appendChild(newItem);
            experienceCount++;
        }

        function addEducation() {
            const container = document.getElementById('educationContainer');
            const newItem = document.createElement('div');
            newItem.className = 'education-item border rounded p-3 mb-3';
            newItem.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Education ${educationCount + 1}</strong>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Degree</label>
                        <input type="text" class="form-control" name="education[${educationCount}][degree]" placeholder="e.g., Bachelor of Science">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Institution</label>
                        <input type="text" class="form-control" name="education[${educationCount}][institution]" placeholder="University Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Graduation Year</label>
                        <input type="text" class="form-control" name="education[${educationCount}][year]" placeholder="e.g., 2020">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">GPA (Optional)</label>
                        <input type="text" class="form-control" name="education[${educationCount}][gpa]" placeholder="e.g., 3.8">
                    </div>
                </div>
            `;
            container.appendChild(newItem);
            educationCount++;
        }

        document.getElementById('skillsInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addSkill();
            }
        });

        function addSkill() {
            const input = document.getElementById('skillsInput');
            const skill = input.value.trim();
            if (skill && !skillsList.includes(skill)) {
                skillsList.push(skill);
                updateSkillsDisplay();
                input.value = '';
            }
        }

        function updateSkillsDisplay() {
            const container = document.getElementById('skillsList');
            container.innerHTML = skillsList.map((skill, index) => `
                <span class="badge bg-primary">
                    ${skill}
                    <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.7rem;" onclick="removeSkill(${index})"></button>
                </span>
            `).join('');
            document.getElementById('skillsHidden').value = skillsList.join(',');
            updateResumePreview();
        }

        function removeSkill(index) {
            skillsList.splice(index, 1);
            updateSkillsDisplay();
        }

        function updateSkillLevel(input, skillName) {
            const value = parseInt(input.value);
            const badge = document.getElementById('badge-' + skillName);
            let level, className;
            
            if (value <= 1) {
                level = 'Beginner';
                className = 'badge-beginner';
            } else if (value <= 2) {
                level = 'Intermediate';
                className = 'badge-intermediate';
            } else if (value <= 4) {
                level = 'Advanced';
                className = 'badge-advanced';
            } else {
                level = 'Expert';
                className = 'badge-expert';
            }
            
            badge.textContent = level;
            badge.className = 'badge-skill ' + className;
        }

        function addCustomSkill() {
            const skillName = prompt('Enter skill name:');
            if (skillName) {
                const container = document.getElementById('skillsAnalysisContainer');
                const skillId = skillName.toLowerCase().replace(/\s+/g, '_');
                const newItem = document.createElement('div');
                newItem.className = 'skill-item';
                newItem.innerHTML = `
                    <div>
                        <div class="skill-name">${skillName}</div>
                        <small class="text-muted">Custom Skill</small>
                    </div>
                    <div class="skill-level">
                        <input type="range" class="form-range" name="skills[${skillId}]" min="1" max="5" value="3" 
                               oninput="updateSkillLevel(this, '${skillId}')">
                        <span class="badge badge-intermediate" id="badge-${skillId}">Intermediate</span>
                    </div>
                `;
                container.appendChild(newItem);
            }
        }

        // =============================================
        // LIVE RESUME PREVIEW - Auto-updates as you type
        // =============================================
        function updateResumePreview() {
            // Personal Info
            const name = document.querySelector('input[name="full_name"]')?.value || 'Your Name';
            const email = document.querySelector('input[name="email"]')?.value || 'email@example.com';
            const phone = document.querySelector('input[name="phone"]')?.value || '+63 000 000 0000';
            const location = document.querySelector('input[name="location"]')?.value || 'Location';
            const summary = document.querySelector('textarea[name="summary"]')?.value || '';

            document.getElementById('previewName').textContent = name || 'Your Name';
            document.getElementById('previewEmail').innerHTML = '<i class="fas fa-envelope me-1"></i>' + (email || 'email@example.com');
            document.getElementById('previewPhone').innerHTML = '<i class="fas fa-phone me-1"></i>' + (phone || '+63 000 000 0000');
            document.getElementById('previewLocation').innerHTML = '<i class="fas fa-map-marker-alt me-1"></i>' + (location || 'Location');

            // Summary
            const summarySection = document.getElementById('previewSummarySection');
            const summaryText = document.getElementById('previewSummary');
            if (summary.trim()) {
                summarySection.style.display = 'block';
                summaryText.textContent = summary;
            } else {
                summarySection.style.display = 'none';
            }

            // Experience
            const experienceSection = document.getElementById('previewExperienceSection');
            const experienceContainer = document.getElementById('previewExperience');
            const experiences = [];
            document.querySelectorAll('#experienceContainer .experience-item').forEach((item, idx) => {
                const title = item.querySelector(`input[name="experience[${idx}][title]"]`)?.value || item.querySelector('input[name*="[title]"]')?.value;
                const company = item.querySelector(`input[name="experience[${idx}][company]"]`)?.value || item.querySelector('input[name*="[company]"]')?.value;
                const startDate = item.querySelector(`input[name="experience[${idx}][start_date]"]`)?.value || item.querySelector('input[name*="[start_date]"]')?.value;
                const endDate = item.querySelector(`input[name="experience[${idx}][end_date]"]`)?.value || item.querySelector('input[name*="[end_date]"]')?.value;
                const desc = item.querySelector(`textarea[name="experience[${idx}][description]"]`)?.value || item.querySelector('textarea[name*="[description]"]')?.value;
                if (title || company) {
                    experiences.push({ title, company, startDate, endDate, desc });
                }
            });
            if (experiences.length > 0) {
                experienceSection.style.display = 'block';
                experienceContainer.innerHTML = experiences.map(exp => `
                    <div class="resume-entry">
                        <div class="resume-entry-title">${exp.title || 'Job Title'}</div>
                        <div class="resume-entry-subtitle">${exp.company || 'Company'}</div>
                        <div class="resume-entry-date">${formatDate(exp.startDate)} - ${exp.endDate ? formatDate(exp.endDate) : 'Present'}</div>
                        ${exp.desc ? `<div class="resume-entry-desc">${exp.desc}</div>` : ''}
                    </div>
                `).join('');
            } else {
                experienceSection.style.display = 'none';
            }

            // Education
            const educationSection = document.getElementById('previewEducationSection');
            const educationContainer = document.getElementById('previewEducation');
            const educations = [];
            document.querySelectorAll('#educationContainer .education-item').forEach((item, idx) => {
                const degree = item.querySelector(`input[name="education[${idx}][degree]"]`)?.value || item.querySelector('input[name*="[degree]"]')?.value;
                const institution = item.querySelector(`input[name="education[${idx}][institution]"]`)?.value || item.querySelector('input[name*="[institution]"]')?.value;
                const year = item.querySelector(`input[name="education[${idx}][year]"]`)?.value || item.querySelector('input[name*="[year]"]')?.value;
                const gpa = item.querySelector(`input[name="education[${idx}][gpa]"]`)?.value || item.querySelector('input[name*="[gpa]"]')?.value;
                if (degree || institution) {
                    educations.push({ degree, institution, year, gpa });
                }
            });
            if (educations.length > 0) {
                educationSection.style.display = 'block';
                educationContainer.innerHTML = educations.map(edu => `
                    <div class="resume-entry">
                        <div class="resume-entry-title">${edu.degree || 'Degree'}</div>
                        <div class="resume-entry-subtitle">${edu.institution || 'Institution'}</div>
                        <div class="resume-entry-date">${edu.year ? 'Graduated ' + edu.year : ''}${edu.gpa ? ' | GPA: ' + edu.gpa : ''}</div>
                    </div>
                `).join('');
            } else {
                educationSection.style.display = 'none';
            }

            // Skills
            const skillsSection = document.getElementById('previewSkillsSection');
            const skillsContainer = document.getElementById('previewSkills');
            if (skillsList.length > 0) {
                skillsSection.style.display = 'block';
                skillsContainer.innerHTML = skillsList.map(skill => `<span class="resume-skill-tag">${skill}</span>`).join('');
            } else {
                skillsSection.style.display = 'none';
            }
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            const [year, month] = dateStr.split('-');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months[parseInt(month) - 1] + ' ' + year;
        }

        // Auto-update preview on any input change
        document.getElementById('resumeForm').addEventListener('input', function(e) {
            updateResumePreview();
        });

        // Also update when experience/education is added or removed
        const origAddExp = addExperience;
        addExperience = function() {
            origAddExp();
            setTimeout(updateResumePreview, 100);
            // Add listeners to new inputs
            document.querySelectorAll('#experienceContainer input, #experienceContainer textarea').forEach(el => {
                el.removeEventListener('input', updateResumePreview);
                el.addEventListener('input', updateResumePreview);
            });
        };

        const origAddEdu = addEducation;
        addEducation = function() {
            origAddEdu();
            setTimeout(updateResumePreview, 100);
            document.querySelectorAll('#educationContainer input').forEach(el => {
                el.removeEventListener('input', updateResumePreview);
                el.addEventListener('input', updateResumePreview);
            });
        };

        function printResume() {
            const content = document.getElementById('resumePreview').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Resume - ${document.querySelector('input[name="full_name"]')?.value || 'Resume'}</title>
                    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                    <style>
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; }
                        .resume-header { display: flex; align-items: flex-start; gap: 24px; padding-bottom: 20px; border-bottom: 3px solid #4f46e5; margin-bottom: 24px; }
                        .resume-photo-area { width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 3px solid #4f46e5; }
                        .resume-photo-area img { width: 100%; height: 100%; object-fit: cover; }
                        .resume-header-info h1 { font-size: 1.8rem; margin: 0 0 8px 0; color: #1e293b; }
                        .resume-contact { display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.9rem; color: #475569; }
                        .resume-contact i { color: #4f46e5; }
                        .resume-section { margin-bottom: 24px; }
                        .resume-section h2 { font-size: 1.1rem; color: #4f46e5; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px; }
                        .resume-entry { margin-bottom: 16px; padding-left: 12px; border-left: 3px solid #e2e8f0; }
                        .resume-entry-title { font-weight: 600; color: #1e293b; }
                        .resume-entry-subtitle { color: #4f46e5; font-size: 0.9rem; }
                        .resume-entry-date { color: #64748b; font-size: 0.85rem; }
                        .resume-entry-desc { color: #475569; font-size: 0.9rem; }
                        .resume-skills { display: flex; flex-wrap: wrap; gap: 8px; }
                        .resume-skill-tag { background: #4f46e5; color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; }
                    </style>
                </head>
                <body>${content}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => { printWindow.print(); }, 500);
        }

        function downloadResume() {
            printResume(); // For now, use print dialog to save as PDF
        }

        // Initialize skill badges
        document.querySelectorAll('input[type="range"]').forEach(input => {
            if (input.name.startsWith('skills[')) {
                const skillName = input.name.match(/\[(.*?)\]/)[1];
                updateSkillLevel(input, skillName);
            }
        });

        // Initial preview update on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateResumePreview();
        });
        updateResumePreview();
    </script>
</body>
</html>
