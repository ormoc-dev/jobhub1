<?php
include 'config.php';

$error = '';
$success = '';
$selectedRole = $_POST['role'] ?? '';

function old($key, $default = '')
{
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default;
}

function oldSelected($key, $value)
{
    return (isset($_POST[$key]) && $_POST[$key] === $value) ? 'selected' : '';
}

function oldChecked($key)
{
    return isset($_POST[$key]) ? 'checked' : '';
}

function ensureEmployeeProfileColumns(PDO $pdo): void
{
    $columns = [
        'preferred_job_type' => "ALTER TABLE employee_profiles ADD COLUMN preferred_job_type VARCHAR(50) NULL AFTER experience_level",
        'preferred_salary_range' => "ALTER TABLE employee_profiles ADD COLUMN preferred_salary_range VARCHAR(50) NULL AFTER preferred_job_type"
    ];

    foreach ($columns as $column => $alterSql) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM employee_profiles LIKE ?");
        $stmt->execute([$column]);
        if ($stmt->rowCount() === 0) {
            $pdo->exec($alterSql);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = sanitizeInput($_POST['role']);
    
    if ($role === 'employee') {
        // Employee registration
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $sex = sanitizeInput($_POST['sex']);
        $dateOfBirth = sanitizeInput($_POST['date_of_birth']);
        $contactNumber = sanitizeInput($_POST['contact_no']);
        $civilStatus = sanitizeInput($_POST['civil_status']);
        $email = sanitizeInput($_POST['email']);
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $highestEducation = sanitizeInput($_POST['highest_education']);
        $location = sanitizeInput($_POST['location']);
        $skills = sanitizeInput($_POST['skills']);
        $experienceLevel = sanitizeInput($_POST['experience_level']);
        $preferredJobType = sanitizeInput($_POST['preferred_job_type']);
        $preferredSalaryRange = sanitizeInput($_POST['preferred_salary_range']);
        
        // Enhanced validation for employee registration
        $validationErrors = [];
        
        // Required field validation
        if (empty(trim($firstName))) {
            $validationErrors[] = 'First name is required and cannot be empty.';
        }
        if (empty(trim($lastName))) {
            $validationErrors[] = 'Last name is required and cannot be empty.';
        }
        if (empty($sex)) {
            $validationErrors[] = 'Sex/Gender is required.';
        }
        if (empty($dateOfBirth)) {
            $validationErrors[] = 'Date of birth is required.';
        }
        if (empty(trim($contactNumber))) {
            $validationErrors[] = 'Contact number is required and cannot be empty.';
        }
        if (empty($civilStatus)) {
            $validationErrors[] = 'Civil status is required.';
        }
        if (empty(trim($email))) {
            $validationErrors[] = 'Email is required and cannot be empty.';
        }
        if (empty(trim($username))) {
            $validationErrors[] = 'Username is required and cannot be empty.';
        }
        if (!empty($username) && !preg_match('/^[A-Za-z]+$/', $username)) {
            $validationErrors[] = 'Username must contain only letters (A-Z, a-z).';
        }
        if (empty($password)) {
            $validationErrors[] = 'Password is required.';
        }
        if (empty($highestEducation)) {
            $validationErrors[] = 'Highest education is required.';
        }
        if (empty(trim($location))) {
            $validationErrors[] = 'Location is required and cannot be empty.';
        }
        if (empty($experienceLevel)) {
            $validationErrors[] = 'Experience level is required.';
        }
        if (empty($preferredJobType)) {
            $validationErrors[] = 'Preferred job type is required.';
        }
        if (empty($preferredSalaryRange)) {
            $validationErrors[] = 'Preferred salary range is required.';
        }
        
        // Additional validation rules
        if (!empty($firstName) && strlen(trim($firstName)) < 2) {
            $validationErrors[] = 'First name must be at least 2 characters long.';
        }
        if (!empty($lastName) && strlen(trim($lastName)) < 2) {
            $validationErrors[] = 'Last name must be at least 2 characters long.';
        }
        if (!empty($firstName) && !preg_match('/^[a-zA-Z\s\-\'\.]+$/', trim($firstName))) {
            $validationErrors[] = 'First name can only contain letters, spaces, hyphens, apostrophes, and periods.';
        }
        if (!empty($lastName) && !preg_match('/^[a-zA-Z\s\-\'\.]+$/', trim($lastName))) {
            $validationErrors[] = 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods.';
        }
        
        if ($password !== $confirmPassword) {
            $validationErrors[] = 'Passwords do not match.';
        }
        if (!empty($password) && strlen($password) > 12) {
            $validationErrors[] = 'Password must be at most 12 characters long.';
        }
        if (!empty($password) && !preg_match('/^[a-zA-Z0-9@#$%&]{1,12}$/', $password)) {
            $validationErrors[] = 'Password must be up to 12 characters with uppercase and lowercase letters, numbers, and one of these special characters: @ # $ % &';
        }
        if (!empty($password) && !preg_match('/[A-Z]/', $password)) {
            $validationErrors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!empty($password) && !preg_match('/[a-z]/', $password)) {
            $validationErrors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!empty($password) && !preg_match('/[0-9]/', $password)) {
            $validationErrors[] = 'Password must contain at least one number.';
        }
        if (!empty($password) && !preg_match('/[@#$%&]/', $password)) {
            $validationErrors[] = 'Password must contain at least one special character: @ # $ % &';
        }
        if (!empty($contactNumber) && !preg_match('/^09\d{9}$/', $contactNumber)) {
            $validationErrors[] = 'Contact number must start with 09 and be 11 digits long.';
        }
        if (!empty($email) && !preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
            $validationErrors[] = 'Email must be a valid Gmail address (@gmail.com).';
        }
        if (!empty($dateOfBirth) && strtotime($dateOfBirth) > strtotime('-18 years')) {
            $validationErrors[] = 'You must be at least 18 years old to register.';
        }
        if (!isset($_POST['terms_agreement'])) {
            $validationErrors[] = 'You must agree to the terms and conditions.';
        }
        
        if (!empty($validationErrors)) {
            $error = implode('<br>', $validationErrors);
        } else {
            try {
                ensureEmployeeProfileColumns($pdo);
                $pdo->beginTransaction();
                
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    throw new Exception('Username or email already exists.');
                }
                
                // Insert user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'employee', 'active')");
                $stmt->execute([$username, $email, $hashedPassword]);
                $userId = $pdo->lastInsertId();
                
                // Generate employee ID
                $employeeId = 'EMP' . str_pad($userId, 6, '0', STR_PAD_LEFT);
                
                // Handle file uploads
                $document1 = '';
                $document2 = '';
                
                if (isset($_FILES['document1']) && $_FILES['document1']['error'] === 0) {
                    $document1 = uploadFile($_FILES['document1'], 'employees');
                }
                if (isset($_FILES['document2']) && $_FILES['document2']['error'] === 0) {
                    $document2 = uploadFile($_FILES['document2'], 'employees');
                }
                
                // Insert employee profile (using contact_no to match database column)
                $stmt = $pdo->prepare("INSERT INTO employee_profiles (user_id, employee_id, first_name, last_name, middle_name, sex, date_of_birth, contact_no, civil_status, highest_education, location, skills, experience_level, preferred_job_type, preferred_salary_range, document1, document2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $employeeId, $firstName, $lastName, $middleName, $sex, $dateOfBirth, $contactNumber, $civilStatus, $highestEducation, $location, $skills, $experienceLevel, $preferredJobType, $preferredSalaryRange, $document1, $document2]);
                
                // Set profile picture from document1 if available (assuming it's a profile picture)
                if ($document1) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$document1, $userId]);
                }
                
                $pdo->commit();
                $success = 'Registration completed successfully! Your account is now active. You can login immediately.';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
        
    } elseif ($role === 'employer') {
        // Employer registration
        $companyName = sanitizeInput($_POST['company_name']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $contactEmail = sanitizeInput($_POST['contact_email']);
        $contactFirstName = sanitizeInput($_POST['contact_first_name']);
        $contactLastName = sanitizeInput($_POST['contact_last_name']);
        $contactPosition = sanitizeInput($_POST['contact_position']);
        $locationAddress = sanitizeInput($_POST['location_address']);
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Enhanced validation for employer registration
        $validationErrors = [];
        
        // Required field validation
        if (empty(trim($companyName))) {
            $validationErrors[] = 'Company name is required and cannot be empty.';
        }
        if (empty(trim($contactNumber))) {
            $validationErrors[] = 'Contact number is required and cannot be empty.';
        }
        if (empty(trim($contactEmail))) {
            $validationErrors[] = 'Contact email is required and cannot be empty.';
        }
        if (empty(trim($contactFirstName))) {
            $validationErrors[] = 'Contact first name is required and cannot be empty.';
        }
        if (empty(trim($contactLastName))) {
            $validationErrors[] = 'Contact last name is required and cannot be empty.';
        }
        if (empty(trim($contactPosition))) {
            $validationErrors[] = 'Contact position is required and cannot be empty.';
        }
        if (empty(trim($locationAddress))) {
            $validationErrors[] = 'Location address is required and cannot be empty.';
        }
        if (empty(trim($username))) {
            $validationErrors[] = 'Username is required and cannot be empty.';
        }
        if (!empty($username) && !preg_match('/^[A-Za-z]+$/', $username)) {
            $validationErrors[] = 'Username must contain only letters (A-Z, a-z).';
        }
        if (empty($password)) {
            $validationErrors[] = 'Password is required.';
        }
        
        // Additional validation rules
        if (!empty($companyName) && strlen(trim($companyName)) < 2) {
            $validationErrors[] = 'Company name must be at least 2 characters long.';
        }
        if (!empty($contactFirstName) && strlen(trim($contactFirstName)) < 2) {
            $validationErrors[] = 'Contact first name must be at least 2 characters long.';
        }
        if (!empty($contactLastName) && strlen(trim($contactLastName)) < 2) {
            $validationErrors[] = 'Contact last name must be at least 2 characters long.';
        }
        if (!empty($contactFirstName) && !preg_match('/^[a-zA-Z\s\-\'\.]+$/', trim($contactFirstName))) {
            $validationErrors[] = 'Contact first name can only contain letters, spaces, hyphens, apostrophes, and periods.';
        }
        if (!empty($contactLastName) && !preg_match('/^[a-zA-Z\s\-\'\.]+$/', trim($contactLastName))) {
            $validationErrors[] = 'Contact last name can only contain letters, spaces, hyphens, apostrophes, and periods.';
        }
        
        if ($password !== $confirmPassword) {
            $validationErrors[] = 'Passwords do not match.';
        }
        if (!empty($password) && strlen($password) > 12) {
            $validationErrors[] = 'Password must be at most 12 characters long.';
        }
        if (!empty($password) && !preg_match('/^[a-zA-Z0-9@#$%&]{1,12}$/', $password)) {
            $validationErrors[] = 'Password must be up to 12 characters with uppercase and lowercase letters, numbers, and one of these special characters: @ # $ % &';
        }
        if (!empty($password) && !preg_match('/[A-Z]/', $password)) {
            $validationErrors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!empty($password) && !preg_match('/[a-z]/', $password)) {
            $validationErrors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!empty($password) && !preg_match('/[0-9]/', $password)) {
            $validationErrors[] = 'Password must contain at least one number.';
        }
        if (!empty($password) && !preg_match('/[@#$%&]/', $password)) {
            $validationErrors[] = 'Password must contain at least one special character: @ # $ % &';
        }
        if (!empty($contactNumber) && !preg_match('/^09\d{9}$/', $contactNumber)) {
            $validationErrors[] = 'Contact number must start with 09 and be 11 digits long.';
        }
        if (!empty($contactEmail) && !preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $contactEmail)) {
            $validationErrors[] = 'Email must be a valid Gmail address (@gmail.com).';
        }
        if (!isset($_POST['terms_agreement'])) {
            $validationErrors[] = 'You must agree to the terms and conditions.';
        }
        
        if (!empty($validationErrors)) {
            $error = implode('<br>', $validationErrors);
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $contactEmail]);
                if ($stmt->fetch()) {
                    throw new Exception('Username or email already exists.');
                }
                
                // Insert user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'employer', 'pending')");
                $stmt->execute([$username, $contactEmail, $hashedPassword]);
                $userId = $pdo->lastInsertId();
                
                // Handle file uploads
                $businessPermit = '';
                $companyLogo = '';
                $supportingDocument = '';
                
                if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] === 0) {
                    $businessPermit = uploadFile($_FILES['business_permit'], 'companies');
                }
                if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === 0) {
                    $companyLogo = uploadFile($_FILES['company_logo'], 'companies');
                }
                if (isset($_FILES['supporting_document']) && $_FILES['supporting_document']['error'] === 0) {
                    $supportingDocument = uploadFile($_FILES['supporting_document'], 'companies');
                }
                
                // Insert company profile
                $stmt = $pdo->prepare("INSERT INTO companies (user_id, company_name, contact_number, contact_email, contact_first_name, contact_last_name, contact_position, location_address, business_permit, company_logo, supporting_document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$userId, $companyName, $contactNumber, $contactEmail, $contactFirstName, $contactLastName, $contactPosition, $locationAddress, $businessPermit, $companyLogo, $supportingDocument]);
                
                // Set company logo as profile picture for the user
                if ($companyLogo) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$companyLogo, $userId]);
                }
                
                $pdo->commit();
                $success = 'Registration completed successfully! Your account is pending admin approval. You will be notified once approved.';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
    body.register-page {
        background: radial-gradient(circle at top, rgba(14, 116, 144, 0.16), transparent 55%),
            radial-gradient(circle at 18% 12%, rgba(59, 130, 246, 0.16), transparent 58%),
            linear-gradient(135deg, #f8fafc 0%, #eef2ff 45%, #f1f5f9 100%);
        min-height: 100vh;
        --brand-primary: #0f766e;
        --brand-secondary: #f59e0b;
        --brand-accent: #3b82f6;
        --brand-ink: #0f172a;
        --surface: #ffffff;
        --surface-muted: #f8fafc;
        --border-soft: rgba(148, 163, 184, 0.28);
    }
    .register-page .form-container {
        background: var(--surface);
        border-radius: 22px;
        border: 1px solid var(--border-soft);
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.14);
        position: relative;
        overflow: hidden;
    }
    .register-page .form-container::after {
        content: "";
        position: absolute;
        top: -120px;
        right: -120px;
        width: 240px;
        height: 240px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.18), transparent 70%);
        pointer-events: none;
    }
    .register-page .register-hero {
        padding: 28px 32px;
        background: linear-gradient(135deg, #0f766e 0%, #14b8a6 55%, #38bdf8 100%);
        color: #f8fafc;
        position: relative;
        overflow: hidden;
    }
    .register-page .register-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.18), transparent 45%);
        pointer-events: none;
    }
    .register-page .register-hero-content {
        position: relative;
        z-index: 1;
    }
    .register-page .register-body {
        padding: 30px 36px 36px;
        background: var(--surface);
    }
    .register-page .logo-img {
        box-shadow: 0 8px 20px rgba(15, 118, 110, 0.35);
    }
    .register-page .role-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        transition: all 0.2s ease;
        cursor: pointer;
        background: #ffffff;
    }
    .register-page .role-image {
        width: 88px;
        height: 88px;
        border-radius: 18px;
        object-fit: cover;
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.12);
    }
    .register-page .role-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(30, 41, 59, 0.12);
        border-color: rgba(15, 118, 110, 0.6);
    }
    .register-page .role-card.selected {
        border-color: var(--brand-primary);
        background: #ecfeff;
        box-shadow: 0 10px 22px rgba(15, 118, 110, 0.2);
    }
    .register-page .form-control,
    .register-page .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: var(--surface-muted);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .register-page .form-control:focus,
    .register-page .form-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 0.2rem rgba(15, 118, 110, 0.15);
        background: #ffffff;
    }
    .register-page .btn-primary {
        background: linear-gradient(135deg, #0f766e, #0ea5e9);
        border: none;
        box-shadow: 0 10px 18px rgba(15, 118, 110, 0.25);
    }
    .register-page .btn-primary:hover {
        background: linear-gradient(135deg, #0f766e, #0284c7);
    }
    .register-page .btn-secondary {
        background: #e2e8f0;
        border: none;
        color: #1e293b;
    }
    .register-page .btn-secondary:hover {
        background: #dbeafe;
    }
    .register-page .text-primary {
        color: var(--brand-primary) !important;
    }
    .register-page .text-success {
        color: var(--brand-secondary) !important;
    }
    .register-page .alert {
        border-radius: 12px;
    }
    .register-page .progress {
        background: #e2e8f0;
    }
    .password-input-wrapper {
        position: relative;
    }
    .password-input-wrapper input {
        padding-right: 40px;
    }
    .password-toggle {
        cursor: pointer;
        transition: color 0.2s ease;
    }
    .password-toggle:hover {
        color: var(--brand-primary) !important;
    }
    .password-toggle:focus {
        outline: none;
        box-shadow: none;
    }
    @media (max-width: 768px) {
        .register-page .register-hero,
        .register-page .register-body {
            padding: 24px 22px;
        }
    }
    </style>
</head>
<body class="bg-light register-page">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="form-container">
                    <div class="register-hero">
                        <div class="register-hero-content text-center">
                            <span class="badge bg-light text-dark mb-2">Secure registration</span>
                            <h2 class="fw-bold mb-1">
                                <img src="worklink.jpg" alt="WORKLINK" class="logo-img me-2" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover;">
                                Join WORKLINK
                            </h2>
                            <p class="mb-0">Create your account and get matched faster</p>
                        </div>
                    </div>
                    <div class="register-body">

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <br><a href="login.php" class="alert-link">Click here to login</a>
                        </div>
                    <?php endif; ?>

                    <!-- Role Selection -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card role-card h-100" data-role="employee" onclick="selectRole('employee', event)">
                                <div class="card-body text-center p-4">
                                    <img src="jobseeker.png" alt="Job Seeker" class="role-image mb-3">
                                    <h5 class="card-title">I'm a Job Seeker</h5>
                                    <p class="card-text">Looking for job opportunities</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card role-card h-100" data-role="employer" onclick="selectRole('employer', event)">
                                <div class="card-body text-center p-4">
                                    <img src="employer.png" alt="Employer" class="role-image mb-3">
                                    <h5 class="card-title">I'm an Employer</h5>
                                    <p class="card-text">Looking to hire talent</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="role-select-error" class="text-danger small mt-1 mb-2" style="display: none;"></div>

                    <form method="POST" action="" enctype="multipart/form-data" id="registrationForm" style="display: <?php echo $selectedRole ? 'block' : 'none'; ?>;" onsubmit="return validateForm(event)">
                        <input type="hidden" name="role" id="selectedRole" value="<?php echo old('role'); ?>">

                        <!-- Employee Form -->
                        <div id="employeeForm" style="display: none;">
                            <h4 class="mb-3 text-primary">Employee Registration</h4>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" id="first_name" placeholder="Enter your first name" autocomplete="given-name" value="<?php echo old('first_name'); ?>">
                                    <div class="form-text">Your given name</div>
                                    <div id="first_name-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" id="last_name" placeholder="Enter your last name" autocomplete="family-name" value="<?php echo old('last_name'); ?>">
                                    <div class="form-text">Your family name</div>
                                    <div id="last_name-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" name="middle_name" id="middle_name" placeholder="Enter your middle name (optional)" autocomplete="additional-name" value="<?php echo old('middle_name'); ?>">
                                    <div class="form-text">Your middle name (optional)</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="sex" class="form-label">Sex *</label>
                                    <select class="form-select" name="sex" id="sex">
                                        <option value="">Select</option>
                                        <option value="Male" <?php echo oldSelected('sex', 'Male'); ?>>Male</option>
                                        <option value="Female" <?php echo oldSelected('sex', 'Female'); ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" name="date_of_birth" id="date_of_birth" autocomplete="bday" value="<?php echo old('date_of_birth'); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact_no" class="form-label">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_no" id="contact_no" placeholder="09xxxxxxxxx" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel" value="<?php echo old('contact_no'); ?>">
                                    <div class="form-text">Must start with 09 and be 11 digits long</div>
                                    <div id="contact_no-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="civil_status" class="form-label">Civil Status *</label>
                                    <select class="form-select" name="civil_status" id="civil_status">
                                        <option value="">Select</option>
                                        <option value="Single" <?php echo oldSelected('civil_status', 'Single'); ?>>Single</option>
                                        <option value="Married" <?php echo oldSelected('civil_status', 'Married'); ?>>Married</option>
                                        <option value="Divorced" <?php echo oldSelected('civil_status', 'Divorced'); ?>>Divorced</option>
                                        <option value="Widowed" <?php echo oldSelected('civil_status', 'Widowed'); ?>>Widowed</option>
                                    </select>
                                    <div id="civil_status-error" class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="highest_education" class="form-label">Educational Attainment *</label>
                                    <select class="form-select" name="highest_education" id="highest_education">
                                    <option value="">Select Educational Attainment</option>
                                        <option value="Elementary" <?php echo oldSelected('highest_education', 'Elementary'); ?>>Elementary</option>
                                        <option value="Junior High School" <?php echo oldSelected('highest_education', 'Junior High School'); ?>>Junior High School</option>
                                        <option value="Senior High School" <?php echo oldSelected('highest_education', 'Senior High School'); ?>>Senior High School</option>
                                        <option value="Junior College" <?php echo oldSelected('highest_education', 'Junior College'); ?>>Junior College</option>
                                        <option value="Graduate Studies" <?php echo oldSelected('highest_education', 'Graduate Studies'); ?>>Graduate Studies</option>
                                        <option value="Post Graduate" <?php echo oldSelected('highest_education', 'Post Graduate'); ?>>Post Graduate</option>
                                        <option value="Senior College" <?php echo oldSelected('highest_education', 'Senior College'); ?>>Senior College</option>
                                        <option value="College Graduate" <?php echo oldSelected('highest_education', 'College Graduate'); ?>>College Graduate</option>
                                        <option value="N/A" <?php echo oldSelected('highest_education', 'N/A'); ?>>N/A</option>
                                </select>
                                <div class="form-text">Select your highest level of education</div>
                                <div id="highest_education-error" class="invalid-feedback"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Location *</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <select class="form-select" name="employee_region" id="employee_region" autocomplete="address-level1" data-selected="<?php echo old('employee_region'); ?>">
                                            <option value="">Select Region</option>
                                        </select>
                                        <div id="employee_region-error" class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="employee_province" id="employee_province" autocomplete="address-level2" disabled data-selected="<?php echo old('employee_province'); ?>">
                                            <option value="">Select Province</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="employee_city" id="employee_city" autocomplete="address-level2" disabled data-selected="<?php echo old('employee_city'); ?>">
                                            <option value="">Select City/Municipality</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="employee_barangay" id="employee_barangay" autocomplete="address-level3" disabled data-selected="<?php echo old('employee_barangay'); ?>">
                                            <option value="">Select Barangay</option>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="location" id="location" value="<?php echo old('location'); ?>">
                                <div class="form-text">Select region, province, city/municipality, and barangay (Philippines only)</div>
                            </div>
                            <div class="mb-3">
                                <label for="skills_select" class="form-label">Skills</label>
                                <select class="form-select" id="skills_select" onchange="validateSkills()">
                                    <option value="">Select one skill</option>
                                    <?php 
                                    $allSkills = getAllUniqueSkills();
                                    $currentSkill = old('skills') ?? '';
                                    foreach ($allSkills as $skill): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($skill); ?>" <?php echo $currentSkill === $skill ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($skill); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="skills" id="skills" value="<?php echo old('skills'); ?>">
                                <div class="form-text">Select one skill</div>
                            </div>
                            <div class="mb-3">
                                <label for="experience_level" class="form-label">Experience Level *</label>
                                <select class="form-select" name="experience_level" id="experience_level">
                                    <option value="">Select Experience Level</option>
                                    <option value="No Experience" <?php echo oldSelected('experience_level', 'No Experience'); ?>>No Experience</option>
                                    <option value="0-1 years" <?php echo oldSelected('experience_level', '0-1 years'); ?>>0-1 years</option>
                                    <option value="1-2 years" <?php echo oldSelected('experience_level', '1-2 years'); ?>>1-2 years</option>
                                    <option value="2-5 years" <?php echo oldSelected('experience_level', '2-5 years'); ?>>2-5 years</option>
                                    <option value="5-10 years" <?php echo oldSelected('experience_level', '5-10 years'); ?>>5-10 years</option>
                                    <option value="10+ years" <?php echo oldSelected('experience_level', '10+ years'); ?>>10+ years</option>
                                </select>
                                <div class="form-text">Select your work experience level</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="preferred_job_type" class="form-label">Preferred Job Type *</label>
                                <select class="form-select" name="preferred_job_type" id="preferred_job_type">
                                        <option value="">Select Job Type</option>
                                    <option value="Full Time" <?php echo oldSelected('preferred_job_type', 'Full Time'); ?>>Full-Time</option>
                                    <option value="Part Time" <?php echo oldSelected('preferred_job_type', 'Part Time'); ?>>Part-Time</option>
                                    <option value="Freelance" <?php echo oldSelected('preferred_job_type', 'Freelance'); ?>>Freelance</option>
                                    <option value="Internship" <?php echo oldSelected('preferred_job_type', 'Internship'); ?>>Internship</option>
                                    <option value="Contract-Based" <?php echo oldSelected('preferred_job_type', 'Contract-Based'); ?>>Contract-Based</option>
                                    <option value="Temporary" <?php echo oldSelected('preferred_job_type', 'Temporary'); ?>>Temporary</option>
                                    <option value="Work From Home" <?php echo oldSelected('preferred_job_type', 'Work From Home'); ?>>Work From Home</option>
                                    <option value="On-Site" <?php echo oldSelected('preferred_job_type', 'On-Site'); ?>>On-Site</option>
                                    <option value="Hybrid" <?php echo oldSelected('preferred_job_type', 'Hybrid'); ?>>Hybrid</option>
                                    <option value="Seasonal" <?php echo oldSelected('preferred_job_type', 'Seasonal'); ?>>Seasonal</option>
                                    </select>
                                    <div class="form-text">Choose the job type you prefer</div>
                                    <div id="preferred_job_type-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="preferred_salary_range" class="form-label">Preferred Salary Range *</label>
                                <select class="form-select" name="preferred_salary_range" id="preferred_salary_range">
                                        <option value="">Select Salary Range</option>
                                    <option value="₱15,000 - ₱25,000" <?php echo oldSelected('preferred_salary_range', '₱15,000 - ₱25,000'); ?>>₱15,000 - ₱25,000</option>
                                    <option value="₱25,000 - ₱35,000" <?php echo oldSelected('preferred_salary_range', '₱25,000 - ₱35,000'); ?>>₱25,000 - ₱35,000</option>
                                    <option value="₱35,000 - ₱50,000" <?php echo oldSelected('preferred_salary_range', '₱35,000 - ₱50,000'); ?>>₱35,000 - ₱50,000</option>
                                    <option value="₱50,000 - ₱75,000" <?php echo oldSelected('preferred_salary_range', '₱50,000 - ₱75,000'); ?>>₱50,000 - ₱75,000</option>
                                    <option value="₱75,000 - ₱100,000" <?php echo oldSelected('preferred_salary_range', '₱75,000 - ₱100,000'); ?>>₱75,000 - ₱100,000</option>
                                    <option value="₱100,000+" <?php echo oldSelected('preferred_salary_range', '₱100,000+'); ?>>₱100,000+</option>
                                    <option value="Negotiable" <?php echo oldSelected('preferred_salary_range', 'Negotiable'); ?>>Negotiable</option>
                                    </select>
                                    <div class="form-text">Choose your expected salary range</div>
                                    <div id="preferred_salary_range-error" class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="document1" class="form-label">Document 1 (Resume/CV)</label>
                                    <input type="file" class="form-control" name="document1" id="document1" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                    <div class="form-text">Upload your resume or CV (JPG, PNG, GIF, PDF)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="document2" class="form-label">Document 2 (Other)</label>
                                    <input type="file" class="form-control" name="document2" id="document2" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                    <div class="form-text">Upload additional documents (JPG, PNG, GIF, PDF)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Employer Form -->
                        <div id="employerForm" style="display: none;">
                            <h4 class="mb-3 text-success">Employer Registration</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" name="company_name" id="company_name" placeholder="Enter your company name" autocomplete="organization" value="<?php echo old('company_name'); ?>">
                                    <div class="form-text">Official name of your company</div>
                                    <div id="company_name-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contact_number" class="form-label">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_number" id="contact_number" placeholder="09xxxxxxxxx" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel" value="<?php echo old('contact_number'); ?>">
                                    <div class="form-text">Must start with 09 and be 11 digits long</div>
                                    <div id="contact_number-error" class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email *</label>
                                <input type="email" class="form-control" name="contact_email" id="contact_email" placeholder="example@gmail.com" autocomplete="email" onkeyup="validateGmailContact()" value="<?php echo old('contact_email'); ?>">
                                <div class="form-text">Must be a valid Gmail address</div>
                                <div id="contact-email-validation" class="mt-1"></div>
                                <div id="contact_email-error" class="invalid-feedback"></div>
                            </div>
                            
                            <!-- Contact Person Information -->
                            <h5 class="mb-3 text-info">Contact Person Information</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="contact_first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="contact_first_name" id="contact_first_name" placeholder="Contact person's first name" autocomplete="given-name" value="<?php echo old('contact_first_name'); ?>">
                                    <div class="form-text">First name of contact person</div>
                                    <div id="contact_first_name-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="contact_last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="contact_last_name" id="contact_last_name" placeholder="Contact person's last name" autocomplete="family-name" value="<?php echo old('contact_last_name'); ?>">
                                    <div class="form-text">Last name of contact person</div>
                                    <div id="contact_last_name-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="contact_position" class="form-label">Position *</label>
                                    <select class="form-select" name="contact_position" id="contact_position" autocomplete="organization-title">
                                        <option value="">Select position</option>
                                        <optgroup label="🗂️ Administrative / Office">
                                            <option value="Company Owner" <?php echo old('contact_position') === 'Company Owner' ? 'selected' : ''; ?>>Company Owner</option>
                                            <option value="Managing Director" <?php echo old('contact_position') === 'Managing Director' ? 'selected' : ''; ?>>Managing Director</option>
                                            <option value="Office Manager" <?php echo old('contact_position') === 'Office Manager' ? 'selected' : ''; ?>>Office Manager</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Administrative Manager" <?php echo old('contact_position') === 'Administrative Manager' ? 'selected' : ''; ?>>Administrative Manager</option>
                                        </optgroup>
                                        <optgroup label="☎️ Customer Service / BPO">
                                            <option value="BPO Company Owner" <?php echo old('contact_position') === 'BPO Company Owner' ? 'selected' : ''; ?>>BPO Company Owner</option>
                                            <option value="Call Center Director" <?php echo old('contact_position') === 'Call Center Director' ? 'selected' : ''; ?>>Call Center Director</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Account Manager" <?php echo old('contact_position') === 'Account Manager' ? 'selected' : ''; ?>>Account Manager</option>
                                            <option value="Contact Center Manager" <?php echo old('contact_position') === 'Contact Center Manager' ? 'selected' : ''; ?>>Contact Center Manager</option>
                                        </optgroup>
                                        <optgroup label="🎓 Education">
                                            <option value="School Owner" <?php echo old('contact_position') === 'School Owner' ? 'selected' : ''; ?>>School Owner</option>
                                            <option value="School Director" <?php echo old('contact_position') === 'School Director' ? 'selected' : ''; ?>>School Director</option>
                                            <option value="Principal" <?php echo old('contact_position') === 'Principal' ? 'selected' : ''; ?>>Principal</option>
                                            <option value="Academic Director" <?php echo old('contact_position') === 'Academic Director' ? 'selected' : ''; ?>>Academic Director</option>
                                            <option value="Training Center Owner" <?php echo old('contact_position') === 'Training Center Owner' ? 'selected' : ''; ?>>Training Center Owner</option>
                                        </optgroup>
                                        <optgroup label="⚙️ Engineering">
                                            <option value="Engineering Firm Owner" <?php echo old('contact_position') === 'Engineering Firm Owner' ? 'selected' : ''; ?>>Engineering Firm Owner</option>
                                            <option value="Engineering Director" <?php echo old('contact_position') === 'Engineering Director' ? 'selected' : ''; ?>>Engineering Director</option>
                                            <option value="Project Director" <?php echo old('contact_position') === 'Project Director' ? 'selected' : ''; ?>>Project Director</option>
                                            <option value="Engineering Manager" <?php echo old('contact_position') === 'Engineering Manager' ? 'selected' : ''; ?>>Engineering Manager</option>
                                            <option value="Technical Manager" <?php echo old('contact_position') === 'Technical Manager' ? 'selected' : ''; ?>>Technical Manager</option>
                                        </optgroup>
                                        <optgroup label="💻 Information Technology (IT)">
                                            <option value="IT Company Owner" <?php echo old('contact_position') === 'IT Company Owner' ? 'selected' : ''; ?>>IT Company Owner</option>
                                            <option value="Chief Technology Officer (CTO)" <?php echo old('contact_position') === 'Chief Technology Officer (CTO)' ? 'selected' : ''; ?>>Chief Technology Officer (CTO)</option>
                                            <option value="IT Director" <?php echo old('contact_position') === 'IT Director' ? 'selected' : ''; ?>>IT Director</option>
                                            <option value="Software Development Manager" <?php echo old('contact_position') === 'Software Development Manager' ? 'selected' : ''; ?>>Software Development Manager</option>
                                            <option value="Product Manager" <?php echo old('contact_position') === 'Product Manager' ? 'selected' : ''; ?>>Product Manager</option>
                                        </optgroup>
                                        <optgroup label="💰 Finance / Accounting">
                                            <option value="Finance Firm Owner" <?php echo old('contact_position') === 'Finance Firm Owner' ? 'selected' : ''; ?>>Finance Firm Owner</option>
                                            <option value="Chief Financial Officer (CFO)" <?php echo old('contact_position') === 'Chief Financial Officer (CFO)' ? 'selected' : ''; ?>>Chief Financial Officer (CFO)</option>
                                            <option value="Finance Director" <?php echo old('contact_position') === 'Finance Director' ? 'selected' : ''; ?>>Finance Director</option>
                                            <option value="Accounting Manager" <?php echo old('contact_position') === 'Accounting Manager' ? 'selected' : ''; ?>>Accounting Manager</option>
                                            <option value="Controller" <?php echo old('contact_position') === 'Controller' ? 'selected' : ''; ?>>Controller</option>
                                        </optgroup>
                                        <optgroup label="🏥 Healthcare / Medical">
                                            <option value="Hospital Owner" <?php echo old('contact_position') === 'Hospital Owner' ? 'selected' : ''; ?>>Hospital Owner</option>
                                            <option value="Hospital Director" <?php echo old('contact_position') === 'Hospital Director' ? 'selected' : ''; ?>>Hospital Director</option>
                                            <option value="Medical Director" <?php echo old('contact_position') === 'Medical Director' ? 'selected' : ''; ?>>Medical Director</option>
                                            <option value="Clinic Owner" <?php echo old('contact_position') === 'Clinic Owner' ? 'selected' : ''; ?>>Clinic Owner</option>
                                            <option value="Healthcare Administrator" <?php echo old('contact_position') === 'Healthcare Administrator' ? 'selected' : ''; ?>>Healthcare Administrator</option>
                                        </optgroup>
                                        <optgroup label="👥 Human Resources (HR)">
                                            <option value="HR Director" <?php echo old('contact_position') === 'HR Director' ? 'selected' : ''; ?>>HR Director</option>
                                            <option value="HR Manager" <?php echo old('contact_position') === 'HR Manager' ? 'selected' : ''; ?>>HR Manager</option>
                                            <option value="People Operations Head" <?php echo old('contact_position') === 'People Operations Head' ? 'selected' : ''; ?>>People Operations Head</option>
                                            <option value="Recruitment Manager" <?php echo old('contact_position') === 'Recruitment Manager' ? 'selected' : ''; ?>>Recruitment Manager</option>
                                            <option value="Talent Director" <?php echo old('contact_position') === 'Talent Director' ? 'selected' : ''; ?>>Talent Director</option>
                                        </optgroup>
                                        <optgroup label="🏭 Manufacturing / Production">
                                            <option value="Factory Owner" <?php echo old('contact_position') === 'Factory Owner' ? 'selected' : ''; ?>>Factory Owner</option>
                                            <option value="Plant Manager" <?php echo old('contact_position') === 'Plant Manager' ? 'selected' : ''; ?>>Plant Manager</option>
                                            <option value="Production Director" <?php echo old('contact_position') === 'Production Director' ? 'selected' : ''; ?>>Production Director</option>
                                            <option value="Operations Director" <?php echo old('contact_position') === 'Operations Director' ? 'selected' : ''; ?>>Operations Director</option>
                                            <option value="Manufacturing Manager" <?php echo old('contact_position') === 'Manufacturing Manager' ? 'selected' : ''; ?>>Manufacturing Manager</option>
                                        </optgroup>
                                        <optgroup label="🚚 Logistics / Warehouse / Supply Chain">
                                            <option value="Logistics Company Owner" <?php echo old('contact_position') === 'Logistics Company Owner' ? 'selected' : ''; ?>>Logistics Company Owner</option>
                                            <option value="Supply Chain Director" <?php echo old('contact_position') === 'Supply Chain Director' ? 'selected' : ''; ?>>Supply Chain Director</option>
                                            <option value="Logistics Manager" <?php echo old('contact_position') === 'Logistics Manager' ? 'selected' : ''; ?>>Logistics Manager</option>
                                            <option value="Warehouse Manager" <?php echo old('contact_position') === 'Warehouse Manager' ? 'selected' : ''; ?>>Warehouse Manager</option>
                                            <option value="Distribution Manager" <?php echo old('contact_position') === 'Distribution Manager' ? 'selected' : ''; ?>>Distribution Manager</option>
                                        </optgroup>
                                        <optgroup label="📈 Marketing / Sales">
                                            <option value="Marketing Agency Owner" <?php echo old('contact_position') === 'Marketing Agency Owner' ? 'selected' : ''; ?>>Marketing Agency Owner</option>
                                            <option value="Sales Director" <?php echo old('contact_position') === 'Sales Director' ? 'selected' : ''; ?>>Sales Director</option>
                                            <option value="Marketing Director" <?php echo old('contact_position') === 'Marketing Director' ? 'selected' : ''; ?>>Marketing Director</option>
                                            <option value="Business Development Manager" <?php echo old('contact_position') === 'Business Development Manager' ? 'selected' : ''; ?>>Business Development Manager</option>
                                            <option value="Commercial Manager" <?php echo old('contact_position') === 'Commercial Manager' ? 'selected' : ''; ?>>Commercial Manager</option>
                                        </optgroup>
                                        <optgroup label="🎨 Creative / Media / Design">
                                            <option value="Creative Agency Owner" <?php echo old('contact_position') === 'Creative Agency Owner' ? 'selected' : ''; ?>>Creative Agency Owner</option>
                                            <option value="Creative Director" <?php echo old('contact_position') === 'Creative Director' ? 'selected' : ''; ?>>Creative Director</option>
                                            <option value="Art Director" <?php echo old('contact_position') === 'Art Director' ? 'selected' : ''; ?>>Art Director</option>
                                            <option value="Studio Manager" <?php echo old('contact_position') === 'Studio Manager' ? 'selected' : ''; ?>>Studio Manager</option>
                                            <option value="Content Director" <?php echo old('contact_position') === 'Content Director' ? 'selected' : ''; ?>>Content Director</option>
                                        </optgroup>
                                        <optgroup label="🏗️ Construction / Infrastructure">
                                            <option value="Construction Company Owner" <?php echo old('contact_position') === 'Construction Company Owner' ? 'selected' : ''; ?>>Construction Company Owner</option>
                                            <option value="Project Director" <?php echo old('contact_position') === 'Project Director' ? 'selected' : ''; ?>>Project Director</option>
                                            <option value="Construction Manager" <?php echo old('contact_position') === 'Construction Manager' ? 'selected' : ''; ?>>Construction Manager</option>
                                            <option value="Site Director" <?php echo old('contact_position') === 'Site Director' ? 'selected' : ''; ?>>Site Director</option>
                                            <option value="Infrastructure Manager" <?php echo old('contact_position') === 'Infrastructure Manager' ? 'selected' : ''; ?>>Infrastructure Manager</option>
                                        </optgroup>
                                        <optgroup label="🍽️ Food / Hospitality / Tourism (Fast-Food Included)">
                                            <option value="Restaurant Owner" <?php echo old('contact_position') === 'Restaurant Owner' ? 'selected' : ''; ?>>Restaurant Owner</option>
                                            <option value="Franchise Owner" <?php echo old('contact_position') === 'Franchise Owner' ? 'selected' : ''; ?>>Franchise Owner</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Hotel/Resort Owner" <?php echo old('contact_position') === 'Hotel/Resort Owner' ? 'selected' : ''; ?>>Hotel/Resort Owner</option>
                                            <option value="Food & Beverage Director" <?php echo old('contact_position') === 'Food & Beverage Director' ? 'selected' : ''; ?>>Food & Beverage Director</option>
                                        </optgroup>
                                        <optgroup label="🛒 Retail / Sales Operations">
                                            <option value="Store Owner" <?php echo old('contact_position') === 'Store Owner' ? 'selected' : ''; ?>>Store Owner</option>
                                            <option value="Retail Director" <?php echo old('contact_position') === 'Retail Director' ? 'selected' : ''; ?>>Retail Director</option>
                                            <option value="Branch Manager" <?php echo old('contact_position') === 'Branch Manager' ? 'selected' : ''; ?>>Branch Manager</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Merchandising Manager" <?php echo old('contact_position') === 'Merchandising Manager' ? 'selected' : ''; ?>>Merchandising Manager</option>
                                        </optgroup>
                                        <optgroup label="🚗 Transportation">
                                            <option value="Transport Company Owner" <?php echo old('contact_position') === 'Transport Company Owner' ? 'selected' : ''; ?>>Transport Company Owner</option>
                                            <option value="Fleet Manager" <?php echo old('contact_position') === 'Fleet Manager' ? 'selected' : ''; ?>>Fleet Manager</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Transport Director" <?php echo old('contact_position') === 'Transport Director' ? 'selected' : ''; ?>>Transport Director</option>
                                            <option value="Terminal Manager" <?php echo old('contact_position') === 'Terminal Manager' ? 'selected' : ''; ?>>Terminal Manager</option>
                                        </optgroup>
                                        <optgroup label="👮 Law Enforcement / Criminology">
                                            <option value="Agency Director" <?php echo old('contact_position') === 'Agency Director' ? 'selected' : ''; ?>>Agency Director</option>
                                            <option value="Station Commander" <?php echo old('contact_position') === 'Station Commander' ? 'selected' : ''; ?>>Station Commander</option>
                                            <option value="Department Head" <?php echo old('contact_position') === 'Department Head' ? 'selected' : ''; ?>>Department Head</option>
                                            <option value="Security Operations Director" <?php echo old('contact_position') === 'Security Operations Director' ? 'selected' : ''; ?>>Security Operations Director</option>
                                            <option value="Law Enforcement Administrator" <?php echo old('contact_position') === 'Law Enforcement Administrator' ? 'selected' : ''; ?>>Law Enforcement Administrator</option>
                                        </optgroup>
                                        <optgroup label="🛡️ Security Services">
                                            <option value="Security Agency Owner" <?php echo old('contact_position') === 'Security Agency Owner' ? 'selected' : ''; ?>>Security Agency Owner</option>
                                            <option value="Security Director" <?php echo old('contact_position') === 'Security Director' ? 'selected' : ''; ?>>Security Director</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Risk Manager" <?php echo old('contact_position') === 'Risk Manager' ? 'selected' : ''; ?>>Risk Manager</option>
                                            <option value="Compliance Head" <?php echo old('contact_position') === 'Compliance Head' ? 'selected' : ''; ?>>Compliance Head</option>
                                        </optgroup>
                                        <optgroup label="🔧 Skilled / Technical (TESDA)">
                                            <option value="Training Center Owner" <?php echo old('contact_position') === 'Training Center Owner' ? 'selected' : ''; ?>>Training Center Owner</option>
                                            <option value="Technical Director" <?php echo old('contact_position') === 'Technical Director' ? 'selected' : ''; ?>>Technical Director</option>
                                            <option value="Workshop Manager" <?php echo old('contact_position') === 'Workshop Manager' ? 'selected' : ''; ?>>Workshop Manager</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Trade School Administrator" <?php echo old('contact_position') === 'Trade School Administrator' ? 'selected' : ''; ?>>Trade School Administrator</option>
                                        </optgroup>
                                        <optgroup label="🌾 Agriculture / Fisheries">
                                            <option value="Farm Owner" <?php echo old('contact_position') === 'Farm Owner' ? 'selected' : ''; ?>>Farm Owner</option>
                                            <option value="Agribusiness Director" <?php echo old('contact_position') === 'Agribusiness Director' ? 'selected' : ''; ?>>Agribusiness Director</option>
                                            <option value="Plantation Manager" <?php echo old('contact_position') === 'Plantation Manager' ? 'selected' : ''; ?>>Plantation Manager</option>
                                            <option value="Fisheries Manager" <?php echo old('contact_position') === 'Fisheries Manager' ? 'selected' : ''; ?>>Fisheries Manager</option>
                                            <option value="Cooperative Manager" <?php echo old('contact_position') === 'Cooperative Manager' ? 'selected' : ''; ?>>Cooperative Manager</option>
                                        </optgroup>
                                        <optgroup label="🌐 Freelance / Online / Remote">
                                            <option value="Agency Owner" <?php echo old('contact_position') === 'Agency Owner' ? 'selected' : ''; ?>>Agency Owner</option>
                                            <option value="Startup Founder" <?php echo old('contact_position') === 'Startup Founder' ? 'selected' : ''; ?>>Startup Founder</option>
                                            <option value="Project Owner" <?php echo old('contact_position') === 'Project Owner' ? 'selected' : ''; ?>>Project Owner</option>
                                            <option value="Platform Operator" <?php echo old('contact_position') === 'Platform Operator' ? 'selected' : ''; ?>>Platform Operator</option>
                                            <option value="Remote Operations Manager" <?php echo old('contact_position') === 'Remote Operations Manager' ? 'selected' : ''; ?>>Remote Operations Manager</option>
                                        </optgroup>
                                        <optgroup label="⚖️ Legal / Government / Public Service">
                                            <option value="Law Firm Owner" <?php echo old('contact_position') === 'Law Firm Owner' ? 'selected' : ''; ?>>Law Firm Owner</option>
                                            <option value="Managing Partner" <?php echo old('contact_position') === 'Managing Partner' ? 'selected' : ''; ?>>Managing Partner</option>
                                            <option value="Legal Director" <?php echo old('contact_position') === 'Legal Director' ? 'selected' : ''; ?>>Legal Director</option>
                                            <option value="Government Agency Head" <?php echo old('contact_position') === 'Government Agency Head' ? 'selected' : ''; ?>>Government Agency Head</option>
                                            <option value="Public Administrator" <?php echo old('contact_position') === 'Public Administrator' ? 'selected' : ''; ?>>Public Administrator</option>
                                        </optgroup>
                                        <optgroup label="✈️ Maritime / Aviation / Transport Specialized">
                                            <option value="Shipping Company Owner" <?php echo old('contact_position') === 'Shipping Company Owner' ? 'selected' : ''; ?>>Shipping Company Owner</option>
                                            <option value="Airline Director" <?php echo old('contact_position') === 'Airline Director' ? 'selected' : ''; ?>>Airline Director</option>
                                            <option value="Fleet Director" <?php echo old('contact_position') === 'Fleet Director' ? 'selected' : ''; ?>>Fleet Director</option>
                                            <option value="Port Manager" <?php echo old('contact_position') === 'Port Manager' ? 'selected' : ''; ?>>Port Manager</option>
                                            <option value="Aviation Operations Manager" <?php echo old('contact_position') === 'Aviation Operations Manager' ? 'selected' : ''; ?>>Aviation Operations Manager</option>
                                        </optgroup>
                                        <optgroup label="🔬 Science / Research / Environment">
                                            <option value="Research Institute Director" <?php echo old('contact_position') === 'Research Institute Director' ? 'selected' : ''; ?>>Research Institute Director</option>
                                            <option value="Laboratory Owner" <?php echo old('contact_position') === 'Laboratory Owner' ? 'selected' : ''; ?>>Laboratory Owner</option>
                                            <option value="R&D Director" <?php echo old('contact_position') === 'R&D Director' ? 'selected' : ''; ?>>R&D Director</option>
                                            <option value="Environmental Program Manager" <?php echo old('contact_position') === 'Environmental Program Manager' ? 'selected' : ''; ?>>Environmental Program Manager</option>
                                            <option value="Science Center Administrator" <?php echo old('contact_position') === 'Science Center Administrator' ? 'selected' : ''; ?>>Science Center Administrator</option>
                                        </optgroup>
                                        <optgroup label="🎭 Arts / Entertainment / Culture">
                                            <option value="Production Company Owner" <?php echo old('contact_position') === 'Production Company Owner' ? 'selected' : ''; ?>>Production Company Owner</option>
                                            <option value="Executive Producer" <?php echo old('contact_position') === 'Executive Producer' ? 'selected' : ''; ?>>Executive Producer</option>
                                            <option value="Creative Director" <?php echo old('contact_position') === 'Creative Director' ? 'selected' : ''; ?>>Creative Director</option>
                                            <option value="Talent Agency Owner" <?php echo old('contact_position') === 'Talent Agency Owner' ? 'selected' : ''; ?>>Talent Agency Owner</option>
                                            <option value="Arts Organization Director" <?php echo old('contact_position') === 'Arts Organization Director' ? 'selected' : ''; ?>>Arts Organization Director</option>
                                        </optgroup>
                                        <optgroup label="✝️ Religion / NGO / Development / Cooperative">
                                            <option value="NGO Founder" <?php echo old('contact_position') === 'NGO Founder' ? 'selected' : ''; ?>>NGO Founder</option>
                                            <option value="Executive Director" <?php echo old('contact_position') === 'Executive Director' ? 'selected' : ''; ?>>Executive Director</option>
                                            <option value="Program Director" <?php echo old('contact_position') === 'Program Director' ? 'selected' : ''; ?>>Program Director</option>
                                            <option value="Cooperative Manager" <?php echo old('contact_position') === 'Cooperative Manager' ? 'selected' : ''; ?>>Cooperative Manager</option>
                                            <option value="Organization Head" <?php echo old('contact_position') === 'Organization Head' ? 'selected' : ''; ?>>Organization Head</option>
                                        </optgroup>
                                        <optgroup label="🧩 Special / Rare Jobs">
                                            <option value="Specialist Firm Owner" <?php echo old('contact_position') === 'Specialist Firm Owner' ? 'selected' : ''; ?>>Specialist Firm Owner</option>
                                            <option value="Operations Director" <?php echo old('contact_position') === 'Operations Director' ? 'selected' : ''; ?>>Operations Director</option>
                                            <option value="Program Head" <?php echo old('contact_position') === 'Program Head' ? 'selected' : ''; ?>>Program Head</option>
                                            <option value="Industry Consultant (Owner)" <?php echo old('contact_position') === 'Industry Consultant (Owner)' ? 'selected' : ''; ?>>Industry Consultant (Owner)</option>
                                        </optgroup>
                                        <optgroup label="🔌 Utilities / Public Services">
                                            <option value="Utilities Company Director" <?php echo old('contact_position') === 'Utilities Company Director' ? 'selected' : ''; ?>>Utilities Company Director</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Plant Manager" <?php echo old('contact_position') === 'Plant Manager' ? 'selected' : ''; ?>>Plant Manager</option>
                                            <option value="Public Services Administrator" <?php echo old('contact_position') === 'Public Services Administrator' ? 'selected' : ''; ?>>Public Services Administrator</option>
                                        </optgroup>
                                        <optgroup label="📡 Telecommunications">
                                            <option value="Telecom Company Owner" <?php echo old('contact_position') === 'Telecom Company Owner' ? 'selected' : ''; ?>>Telecom Company Owner</option>
                                            <option value="Network Director" <?php echo old('contact_position') === 'Network Director' ? 'selected' : ''; ?>>Network Director</option>
                                            <option value="Operations Director" <?php echo old('contact_position') === 'Operations Director' ? 'selected' : ''; ?>>Operations Director</option>
                                            <option value="Technical Operations Manager" <?php echo old('contact_position') === 'Technical Operations Manager' ? 'selected' : ''; ?>>Technical Operations Manager</option>
                                        </optgroup>
                                        <optgroup label="⛏️ Mining / Geology">
                                            <option value="Mining Company Owner" <?php echo old('contact_position') === 'Mining Company Owner' ? 'selected' : ''; ?>>Mining Company Owner</option>
                                            <option value="Mine Director" <?php echo old('contact_position') === 'Mine Director' ? 'selected' : ''; ?>>Mine Director</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Geology Manager" <?php echo old('contact_position') === 'Geology Manager' ? 'selected' : ''; ?>>Geology Manager</option>
                                        </optgroup>
                                        <optgroup label="🛢️ Oil / Gas / Energy">
                                            <option value="Energy Company Owner" <?php echo old('contact_position') === 'Energy Company Owner' ? 'selected' : ''; ?>>Energy Company Owner</option>
                                            <option value="Energy Operations Director" <?php echo old('contact_position') === 'Energy Operations Director' ? 'selected' : ''; ?>>Energy Operations Director</option>
                                            <option value="Plant Manager" <?php echo old('contact_position') === 'Plant Manager' ? 'selected' : ''; ?>>Plant Manager</option>
                                            <option value="Project Director" <?php echo old('contact_position') === 'Project Director' ? 'selected' : ''; ?>>Project Director</option>
                                        </optgroup>
                                        <optgroup label="⚗️ Chemical / Industrial">
                                            <option value="Chemical Plant Owner" <?php echo old('contact_position') === 'Chemical Plant Owner' ? 'selected' : ''; ?>>Chemical Plant Owner</option>
                                            <option value="Industrial Director" <?php echo old('contact_position') === 'Industrial Director' ? 'selected' : ''; ?>>Industrial Director</option>
                                            <option value="Production Manager" <?php echo old('contact_position') === 'Production Manager' ? 'selected' : ''; ?>>Production Manager</option>
                                            <option value="Quality Director" <?php echo old('contact_position') === 'Quality Director' ? 'selected' : ''; ?>>Quality Director</option>
                                        </optgroup>
                                        <optgroup label="🩺 Allied Health / Special Education / Therapy">
                                            <option value="Therapy Center Owner" <?php echo old('contact_position') === 'Therapy Center Owner' ? 'selected' : ''; ?>>Therapy Center Owner</option>
                                            <option value="Clinical Director" <?php echo old('contact_position') === 'Clinical Director' ? 'selected' : ''; ?>>Clinical Director</option>
                                            <option value="Healthcare Program Manager" <?php echo old('contact_position') === 'Healthcare Program Manager' ? 'selected' : ''; ?>>Healthcare Program Manager</option>
                                            <option value="Special Education Director" <?php echo old('contact_position') === 'Special Education Director' ? 'selected' : ''; ?>>Special Education Director</option>
                                        </optgroup>
                                        <optgroup label="🏋️ Sports / Fitness / Recreation">
                                            <option value="Gym Owner" <?php echo old('contact_position') === 'Gym Owner' ? 'selected' : ''; ?>>Gym Owner</option>
                                            <option value="Sports Director" <?php echo old('contact_position') === 'Sports Director' ? 'selected' : ''; ?>>Sports Director</option>
                                            <option value="Fitness Operations Manager" <?php echo old('contact_position') === 'Fitness Operations Manager' ? 'selected' : ''; ?>>Fitness Operations Manager</option>
                                            <option value="Recreation Center Manager" <?php echo old('contact_position') === 'Recreation Center Manager' ? 'selected' : ''; ?>>Recreation Center Manager</option>
                                        </optgroup>
                                        <optgroup label="👗 Fashion / Apparel / Beauty">
                                            <option value="Fashion Brand Owner" <?php echo old('contact_position') === 'Fashion Brand Owner' ? 'selected' : ''; ?>>Fashion Brand Owner</option>
                                            <option value="Creative Director" <?php echo old('contact_position') === 'Creative Director' ? 'selected' : ''; ?>>Creative Director</option>
                                            <option value="Retail Operations Manager" <?php echo old('contact_position') === 'Retail Operations Manager' ? 'selected' : ''; ?>>Retail Operations Manager</option>
                                            <option value="Salon Owner" <?php echo old('contact_position') === 'Salon Owner' ? 'selected' : ''; ?>>Salon Owner</option>
                                        </optgroup>
                                        <optgroup label="🏡 Home / Personal Services">
                                            <option value="Service Business Owner" <?php echo old('contact_position') === 'Service Business Owner' ? 'selected' : ''; ?>>Service Business Owner</option>
                                            <option value="Operations Manager" <?php echo old('contact_position') === 'Operations Manager' ? 'selected' : ''; ?>>Operations Manager</option>
                                            <option value="Agency Manager" <?php echo old('contact_position') === 'Agency Manager' ? 'selected' : ''; ?>>Agency Manager</option>
                                        </optgroup>
                                        <optgroup label="🏦 Insurance / Risk / Banking">
                                            <option value="Insurance Company Owner" <?php echo old('contact_position') === 'Insurance Company Owner' ? 'selected' : ''; ?>>Insurance Company Owner</option>
                                            <option value="Branch Manager" <?php echo old('contact_position') === 'Branch Manager' ? 'selected' : ''; ?>>Branch Manager</option>
                                            <option value="Risk Director" <?php echo old('contact_position') === 'Risk Director' ? 'selected' : ''; ?>>Risk Director</option>
                                            <option value="Banking Operations Manager" <?php echo old('contact_position') === 'Banking Operations Manager' ? 'selected' : ''; ?>>Banking Operations Manager</option>
                                        </optgroup>
                                        <optgroup label="💼 Micro Jobs / Informal / Daily Wage">
                                            <option value="Small Business Owner" <?php echo old('contact_position') === 'Small Business Owner' ? 'selected' : ''; ?>>Small Business Owner</option>
                                            <option value="Contractor" <?php echo old('contact_position') === 'Contractor' ? 'selected' : ''; ?>>Contractor</option>
                                            <option value="Project Supervisor" <?php echo old('contact_position') === 'Project Supervisor' ? 'selected' : ''; ?>>Project Supervisor</option>
                                            <option value="Operations Head" <?php echo old('contact_position') === 'Operations Head' ? 'selected' : ''; ?>>Operations Head</option>
                                        </optgroup>
                                        <optgroup label="🏠 Real Estate / Property">
                                            <option value="Property Developer" <?php echo old('contact_position') === 'Property Developer' ? 'selected' : ''; ?>>Property Developer</option>
                                            <option value="Real Estate Company Owner" <?php echo old('contact_position') === 'Real Estate Company Owner' ? 'selected' : ''; ?>>Real Estate Company Owner</option>
                                            <option value="Property Manager" <?php echo old('contact_position') === 'Property Manager' ? 'selected' : ''; ?>>Property Manager</option>
                                            <option value="Leasing Director" <?php echo old('contact_position') === 'Leasing Director' ? 'selected' : ''; ?>>Leasing Director</option>
                                        </optgroup>
                                        <optgroup label="📊 Entrepreneurship / Business / Corporate">
                                            <option value="Entrepreneur" <?php echo old('contact_position') === 'Entrepreneur' ? 'selected' : ''; ?>>Entrepreneur</option>
                                            <option value="Founder / Co-Founder" <?php echo old('contact_position') === 'Founder / Co-Founder' ? 'selected' : ''; ?>>Founder / Co-Founder</option>
                                            <option value="Chief Executive Officer (CEO)" <?php echo old('contact_position') === 'Chief Executive Officer (CEO)' ? 'selected' : ''; ?>>Chief Executive Officer (CEO)</option>
                                            <option value="Managing Director" <?php echo old('contact_position') === 'Managing Director' ? 'selected' : ''; ?>>Managing Director</option>
                                            <option value="Business Owner" <?php echo old('contact_position') === 'Business Owner' ? 'selected' : ''; ?>>Business Owner</option>
                                        </optgroup>
                                    </select>
                                    <div class="form-text">Job title of contact person</div>
                                    <div id="contact_position-error" class="invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Location Address *</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <select class="form-select" name="employer_region" id="employer_region" autocomplete="address-level1" data-selected="<?php echo old('employer_region'); ?>">
                                            <option value="">Select Region</option>
                                        </select>
                                        <div id="employer_region-error" class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="employer_province" id="employer_province" autocomplete="address-level2" disabled data-selected="<?php echo old('employer_province'); ?>">
                                            <option value="">Select Province</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="employer_city" id="employer_city" autocomplete="address-level2" disabled data-selected="<?php echo old('employer_city'); ?>">
                                            <option value="">Select City/Municipality</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="employer_barangay" id="employer_barangay" autocomplete="address-level3" disabled data-selected="<?php echo old('employer_barangay'); ?>">
                                            <option value="">Select Barangay</option>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="location_address" id="location_address" value="<?php echo old('location_address'); ?>">
                                <div class="form-text">Select region, province, city/municipality, and barangay (Philippines only)</div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="business_permit" class="form-label">Business Permit</label>
                                    <input type="file" class="form-control" name="business_permit" id="business_permit" accept=".pdf,.jpg,.png">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="company_logo" class="form-label">Company Logo</label>
                                    <input type="file" class="form-control" name="company_logo" id="company_logo" accept=".jpg,.png,.gif">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="company_id" class="form-label">Company ID/VALID ID</label>
                                    <input type="file" class="form-control" name="company_id" id="company_id" accept=".pdf,.jpg,.png">
                                </div>
                            </div>
                        </div>

                        <!-- Common Account Fields -->
                        <div id="accountFields" style="display: none;">
                            <h4 class="mb-3 text-info">Account Information</h4>
                            <div class="mb-3">
                                <label for="email" class="form-label employee-only">Email Address *</label>
                                <input type="email" class="form-control employee-only" name="email" id="email" placeholder="example@gmail.com" autocomplete="email" onkeyup="validateGmail()" value="<?php echo old('email'); ?>">
                                <div class="form-text employee-only">Must be a valid Gmail address</div>
                                <div id="email-validation" class="mt-1"></div>
                                <div id="email-error" class="invalid-feedback"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" id="username" placeholder="Choose a unique username" autocomplete="username" pattern="[A-Za-z]+" title="Letters only (A-Z, a-z)" value="<?php echo old('username'); ?>">
                                    <div class="form-text">This will be your login username</div>
                                    <div id="username-error" class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="password-input-wrapper position-relative">
                                        <input type="password" class="form-control" name="password" id="password" maxlength="12" autocomplete="new-password" onkeyup="checkPasswordStrength()">
                                        <button type="button" class="btn btn-link password-toggle" onmousedown="showPassword('password', 'password-toggle-icon')" onmouseup="hidePassword('password', 'password-toggle-icon')" onmouseleave="hidePassword('password', 'password-toggle-icon')" ontouchstart="showPassword('password', 'password-toggle-icon')" ontouchend="hidePassword('password', 'password-toggle-icon')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0; color: #6c757d; z-index: 10;">
                                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                                        </button>
                                        <div id="password-error" class="invalid-feedback"></div>
                                    </div>
                                    <div class="password-requirements mt-2">
                                        <small class="form-text">Password must contain:</small>
                                        <ul class="list-unstyled small mb-0">
                                            <li id="pw-req-length" class="text-muted"><i class="far fa-circle me-1"></i>Maximum 12 characters</li>
                                            <li id="pw-req-number" class="text-muted"><i class="far fa-circle me-1"></i>At least 1 number</li>
                                            <li id="pw-req-lower" class="text-muted"><i class="far fa-circle me-1"></i>At least 1 lowercase letter</li>
                                            <li id="pw-req-upper" class="text-muted"><i class="far fa-circle me-1"></i>At least 1 uppercase letter</li>
                                            <li id="pw-req-special" class="text-muted"><i class="far fa-circle me-1"></i>At least 1 special character (@ # $ % &)</li>
                                        </ul>
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar" id="password-strength-bar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small id="password-strength-text" class="form-text">Password strength</small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <div class="password-input-wrapper position-relative">
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" maxlength="12" autocomplete="new-password" onkeyup="checkPasswordMatch()">
                                        <button type="button" class="btn btn-link password-toggle" onmousedown="showPassword('confirm_password', 'confirm-password-toggle-icon')" onmouseup="hidePassword('confirm_password', 'confirm-password-toggle-icon')" onmouseleave="hidePassword('confirm_password', 'confirm-password-toggle-icon')" ontouchstart="showPassword('confirm_password', 'confirm-password-toggle-icon')" ontouchend="hidePassword('confirm_password', 'confirm-password-toggle-icon')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0; color: #6c757d; z-index: 10;">
                                            <i class="fas fa-eye" id="confirm-password-toggle-icon"></i>
                                        </button>
                                        <div id="confirm_password-error" class="invalid-feedback"></div>
                                    </div>
                                    <div id="password-match" class="form-text"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="terms_agreement" id="terms_agreement" required <?php echo oldChecked('terms_agreement'); ?>>
                                <label class="form-check-label" for="terms_agreement">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a> *
                                </label>
                                <div id="terms_agreement-error" class="invalid-feedback"></div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="button" class="btn btn-primary btn-lg px-5" onclick="validateForm(event)">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-5 ms-3" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted">Already have an account? 
                            <a href="login.php" class="text-primary">Sign in here</a>
                        </p>
                        <a href="index.php" class="text-muted">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Account Registration</h6>
                    <p>All Jobseekers and Employers must provide accurate and complete information when creating an account.</p>

                    <h6>2. User Responsibility</h6>
                    <p>Users are responsible for all activities conducted through their registered accounts.</p>

                    <h6>3. Job Posting Accuracy</h6>
                    <p>Employers must post only legitimate, clear, and accurate job information.</p>

                    <h6>4. Profile Authenticity</h6>
                    <p>Jobseekers must provide truthful details regarding their skills, experience, and qualifications.</p>

                    <h6>5. Data Privacy</h6>
                    <p>WORKLINK protects users’ personal information in compliance with the Data Privacy Act of 2012.</p>

                    <h6>6. Prohibited Activities</h6>
                    <p>Fraud, spam, fake job postings, and any illegal activities are strictly prohibited on the platform.</p>

                    <h6>7. Subscription and Payments</h6>
                    <p>Subscription fees are non-refundable unless otherwise stated in WORKLINK’s policies.</p>

                    <h6>8. Account Suspension or Termination</h6>
                    <p>WORKLINK reserves the right to suspend or terminate accounts that violate these Terms and Conditions.</p>

                    <h6>9. Limitation of Liability</h6>
                    <p>WORKLINK is not responsible for employment agreements, salaries, or disputes between Employers and Jobseekers.</p>

                    <h6>10. Changes to Terms</h6>
                    <p>WORKLINK may modify these Terms and Conditions at any time, and users will be notified through the platform.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Information Collection</h6>
                    <p>WORKLINK collects personal information such as names, contact details, resumes, and employment-related data provided by users.</p>

                    <h6>2. Use of Information</h6>
                    <p>Collected information is used to facilitate job matching, communication, and platform improvement.</p>

                    <h6>3. Data Protection</h6>
                    <p>WORKLINK implements appropriate security measures to protect user data from unauthorized access, loss, or misuse.</p>

                    <h6>4. Data Sharing</h6>
                    <p>User information is shared only between Employers and Jobseekers for recruitment purposes and is not sold to third parties.</p>

                    <h6>5. User Consent</h6>
                    <p>By using the platform, users consent to the collection and processing of their personal data.</p>

                    <h6>6. Access and Control</h6>
                    <p>Users may view, update, or delete their personal information through their account settings.</p>

                    <h6>7. Data Retention</h6>
                    <p>Personal data is retained only as long as necessary to fulfill the platform’s purpose.</p>

                    <h6>8. Cookies and Tracking</h6>
                    <p>WORKLINK may use cookies to enhance user experience and improve system performance.</p>

                    <h6>9. Third-Party Services</h6>
                    <p>WORKLINK may integrate third-party tools, which are required to comply with applicable data protection laws.</p>

                    <h6>10. Policy Updates</h6>
                    <p>WORKLINK reserves the right to update this Privacy Policy and will notify users of any changes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gmail validation functions
        function validateGmail() {
            const email = document.getElementById('email').value;
            const validationDiv = document.getElementById('email-validation');
            
            if (!email) {
                validationDiv.innerHTML = '';
                return;
            }
            
            const isValidGmail = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i.test(email);
            
            if (isValidGmail) {
                validationDiv.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Valid Gmail address</small>';
                document.getElementById('email').classList.remove('is-invalid');
                document.getElementById('email').classList.add('is-valid');
            } else {
                validationDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Only Gmail addresses are allowed (@gmail.com)</small>';
                document.getElementById('email').classList.remove('is-valid');
                document.getElementById('email').classList.add('is-invalid');
            }
        }
        
        function validateGmailContact() {
            const email = document.getElementById('contact_email').value;
            const validationDiv = document.getElementById('contact-email-validation');
            
            if (!email) {
                validationDiv.innerHTML = '';
                return;
            }
            
            const isValidGmail = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i.test(email);
            
            if (isValidGmail) {
                validationDiv.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Valid Gmail address</small>';
                document.getElementById('contact_email').classList.remove('is-invalid');
                document.getElementById('contact_email').classList.add('is-valid');
            } else {
                validationDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Only Gmail addresses are allowed (@gmail.com)</small>';
                document.getElementById('contact_email').classList.remove('is-valid');
                document.getElementById('contact_email').classList.add('is-invalid');
            }
        }

        function getSelectedSkills() {
            const skillsSelect = document.getElementById('skills_select');
            if (!skillsSelect) {
                return [];
            }

            return Array.from(skillsSelect.selectedOptions)
                .map(option => option.value.trim())
                .filter(value => value.length > 0);
        }

        function syncSkillsHiddenInput(selectedSkills) {
            const hiddenSkillsInput = document.getElementById('skills');
            if (!hiddenSkillsInput) {
                return;
            }

            const uniqueSkills = Array.from(new Set(selectedSkills));
            hiddenSkillsInput.value = uniqueSkills.join(', ');
        }

        // Skills validation - allow only 1 skill
        function validateSkills() {
            const skillsSelect = document.getElementById('skills_select');
            if (!skillsSelect) {
                return;
            }

            const selectedSkills = getSelectedSkills();
            const uniqueSkills = Array.from(new Set(selectedSkills));
            syncSkillsHiddenInput(uniqueSkills);
            
            if (uniqueSkills.length > 1) {
                skillsSelect.setCustomValidity('Only 1 skill allowed');
                skillsSelect.classList.add('is-invalid');
            } else {
                skillsSelect.setCustomValidity('');
                skillsSelect.classList.remove('is-invalid');
            }
        }

        function initializeSkillsSelect() {
            const skillsSelect = document.getElementById('skills_select');
            const hiddenSkillsInput = document.getElementById('skills');
            if (!skillsSelect || !hiddenSkillsInput || !hiddenSkillsInput.value) {
                return;
            }

            const savedSkills = hiddenSkillsInput.value
                .split(',')
                .map(skill => skill.trim())
                .filter(skill => skill.length > 0);

            if (savedSkills.length === 0) {
                return;
            }

            Array.from(skillsSelect.options).forEach(option => {
                if (savedSkills.includes(option.value)) {
                    option.selected = true;
                }
            });

            validateSkills();
        }

        function evaluatePassword(password) {
            const checks = {
                length: password.length > 0 && password.length <= 12,
                number: /[0-9]/.test(password),
                lower: /[a-z]/.test(password),
                upper: /[A-Z]/.test(password),
                special: /[@#$%&]/.test(password)
            };

            const allRequired = Object.values(checks).every(Boolean);
            const hasAllChars = checks.number && checks.lower && checks.upper && checks.special;
            const meetsMediumLength = password.length >= 8;
            let strengthLabel = 'WEAK';
            let strengthClass = 'bg-danger';
            let textClass = 'text-danger';
            let percent = Math.round((Object.values(checks).filter(Boolean).length / 5) * 50);

            if (allRequired) {
                strengthLabel = 'STRONG';
                strengthClass = 'bg-success';
                textClass = 'text-success';
                percent = 100;
            } else if (hasAllChars && meetsMediumLength) {
                strengthLabel = 'MEDIUM';
                strengthClass = 'bg-warning';
                textClass = 'text-warning';
                percent = 75;
            }

            return { checks, allRequired, strengthLabel, strengthClass, textClass, percent };
        }

        function updateRequirementItem(id, isMet) {
            const item = document.getElementById(id);
            if (!item) return;
            const icon = item.querySelector('i');
            if (icon) {
                icon.className = isMet ? 'fas fa-check-circle me-1' : 'far fa-circle me-1';
            }
            item.className = isMet ? 'text-success' : 'text-muted';
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');

            if (!password) {
                updateRequirementItem('pw-req-length', false);
                updateRequirementItem('pw-req-number', false);
                updateRequirementItem('pw-req-lower', false);
                updateRequirementItem('pw-req-upper', false);
                updateRequirementItem('pw-req-special', false);
                strengthBar.style.width = '0%';
                strengthBar.className = 'progress-bar';
                strengthBar.style.boxShadow = 'none';
                strengthText.textContent = 'Password strength';
                strengthText.className = 'form-text';
                return;
            }

            const evaluation = evaluatePassword(password);
            updateRequirementItem('pw-req-length', evaluation.checks.length);
            updateRequirementItem('pw-req-number', evaluation.checks.number);
            updateRequirementItem('pw-req-lower', evaluation.checks.lower);
            updateRequirementItem('pw-req-upper', evaluation.checks.upper);
            updateRequirementItem('pw-req-special', evaluation.checks.special);

            strengthBar.style.width = evaluation.percent + '%';
            strengthBar.className = 'progress-bar ' + evaluation.strengthClass;
            strengthBar.style.boxShadow = evaluation.allRequired
                ? (evaluation.strengthLabel === 'STRONG'
                    ? '0 0 10px rgba(25, 135, 84, 0.5)'
                    : '0 0 10px rgba(255, 193, 7, 0.5)')
                : '0 0 10px rgba(220, 53, 69, 0.5)';
            strengthText.textContent = 'Password strength: ' + evaluation.strengthLabel;
            strengthText.className = 'form-text ' + evaluation.textClass;
        }
        
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (!confirmPassword) {
                matchDiv.textContent = '';
                matchDiv.className = 'form-text';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.textContent = 'Passwords match ✓';
                matchDiv.className = 'form-text text-success';
            } else {
                matchDiv.textContent = 'Passwords do not match ✗';
                matchDiv.className = 'form-text text-danger';
            }
        }

        // Show password (on mousedown)
        function showPassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput && toggleIcon) {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        // Hide password (on mouseup or mouseleave)
        function hidePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput && toggleIcon) {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Contact number input mask
        function setupContactNumberMask() {
            const contactInputs = document.querySelectorAll('input[name="contact_no"], input[name="contact_number"]');
            contactInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    // Remove any non-digit characters
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // Ensure it starts with 09
                    if (value.length > 0 && !value.startsWith('09')) {
                        value = '09' + value.replace(/^09/, '');
                    }
                    
                    // Limit to 11 digits
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    
                    e.target.value = value;
                });
            });
        }

        function setupAutoUppercaseInputs() {
            const upperInputs = document.querySelectorAll(
                '#registrationForm input[type="text"], ' +
                '#registrationForm input[type="search"], ' +
                '#registrationForm input[type="url"], ' +
                '#registrationForm textarea'
            );

            upperInputs.forEach(input => {
                if (input.name === 'username' || input.id === 'username') {
                    return;
                }
                input.addEventListener('input', function() {
                    const start = input.selectionStart;
                    const end = input.selectionEnd;
                    const upperValue = input.value.toUpperCase();

                    if (input.value !== upperValue) {
                        input.value = upperValue;
                        if (start !== null && end !== null) {
                            input.setSelectionRange(start, end);
                        }
                    }
                });
            });
        }

        function setupUsernameLettersOnly() {
            const usernameInput = document.getElementById('username');
            if (!usernameInput) {
                return;
            }

            usernameInput.addEventListener('input', function() {
                const start = usernameInput.selectionStart;
                const end = usernameInput.selectionEnd;
                const filtered = usernameInput.value.replace(/[^A-Za-z]/g, '');

                if (usernameInput.value !== filtered) {
                    usernameInput.value = filtered;
                    if (start !== null && end !== null) {
                        usernameInput.setSelectionRange(start, end);
                    }
                }
            });
        }

        const PSGC_BASE_URL = 'https://psgc.gitlab.io/api';

        async function fetchPsgcJson(url) {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) {
                throw new Error('Failed to load location data.');
            }
            return response.json();
        }

        function setSelectOptions(select, items, placeholder) {
            select.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            select.appendChild(placeholderOption);

            items
                .slice()
                .sort((a, b) => a.name.localeCompare(b.name))
                .forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.code;
                    option.textContent = item.name;
                    select.appendChild(option);
                });
        }

        function initPhilippinesAddress(config) {
            const regionSelect = config.regionSelect;
            const provinceSelect = config.provinceSelect;
            const citySelect = config.citySelect;
            const barangaySelect = config.barangaySelect;
            const outputInput = config.outputInput;

            if (!regionSelect || !provinceSelect || !citySelect || !barangaySelect || !outputInput) {
                return;
            }

            const state = {
                regionName: '',
                provinceName: '',
                cityName: '',
                barangayName: '',
                hasProvinces: true
            };

            const resetSelect = (select, placeholder) => {
                select.disabled = true;
                setSelectOptions(select, [], placeholder);
            };

            const updateOutput = () => {
                const hasRegion = Boolean(state.regionName);
                const hasProvince = state.hasProvinces ? Boolean(state.provinceName) : true;
                const hasCity = Boolean(state.cityName);
                const hasBarangay = Boolean(state.barangayName);

                if (hasRegion && hasProvince && hasCity && hasBarangay) {
                    const parts = [
                        state.barangayName,
                        state.cityName
                    ];
                    if (state.hasProvinces) {
                        parts.push(state.provinceName);
                    }
                    parts.push(state.regionName);
                    outputInput.value = parts.join(', ');
                } else {
                    outputInput.value = '';
                }
            };

            const preset = {
                regionCode: regionSelect.dataset.selected || '',
                provinceCode: provinceSelect.dataset.selected || '',
                cityCode: citySelect.dataset.selected || '',
                barangayCode: barangaySelect.dataset.selected || ''
            };
            let isPrefill = Boolean(preset.regionCode);

            const loadRegions = async () => {
                try {
                    const regions = await fetchPsgcJson(`${PSGC_BASE_URL}/regions/`);
                    setSelectOptions(regionSelect, regions, 'Select Region');
                    regionSelect.disabled = false;
                    if (preset.regionCode) {
                        regionSelect.value = preset.regionCode;
                        regionSelect.dispatchEvent(new Event('change'));
                    }
                } catch (error) {
                    setSelectOptions(regionSelect, [], 'Unable to load regions');
                    regionSelect.disabled = true;
                }
            };

            const loadProvinces = async (regionCode) => {
                const provinces = await fetchPsgcJson(`${PSGC_BASE_URL}/regions/${regionCode}/provinces/`);
                setSelectOptions(provinceSelect, provinces, 'Select Province');
                provinceSelect.disabled = false;
                return provinces;
            };

            const loadCitiesByProvince = async (provinceCode) => {
                const cities = await fetchPsgcJson(`${PSGC_BASE_URL}/provinces/${provinceCode}/cities-municipalities/`);
                setSelectOptions(citySelect, cities, 'Select City/Municipality');
                citySelect.disabled = false;
            };

            const loadCitiesByRegion = async (regionCode) => {
                const cities = await fetchPsgcJson(`${PSGC_BASE_URL}/regions/${regionCode}/cities-municipalities/`);
                setSelectOptions(citySelect, cities, 'Select City/Municipality');
                citySelect.disabled = false;
            };

            const loadBarangays = async (cityCode) => {
                // Clear and disable barangay select first
                resetSelect(barangaySelect, 'Loading barangays...');
                
                try {
                    const barangays = await fetchPsgcJson(`${PSGC_BASE_URL}/cities-municipalities/${cityCode}/barangays/`);
                    setSelectOptions(barangaySelect, barangays, 'Select Barangay');
                    barangaySelect.disabled = false;
                } catch (error) {
                    resetSelect(barangaySelect, 'Unable to load barangays');
                    console.error('Error loading barangays:', error);
                }
            };

            regionSelect.addEventListener('change', async function() {
                state.regionName = this.options[this.selectedIndex]?.text || '';
                state.provinceName = '';
                state.cityName = '';
                state.barangayName = '';
                outputInput.value = '';

                resetSelect(provinceSelect, 'Select Province');
                resetSelect(citySelect, 'Select City/Municipality');
                resetSelect(barangaySelect, 'Select Barangay');

                if (!this.value) {
                    return;
                }

                try {
                    const provinces = await loadProvinces(this.value);
                    if (!provinces.length) {
                        state.hasProvinces = false;
                        resetSelect(provinceSelect, 'No Province (NCR)');
                        await loadCitiesByRegion(this.value);
                        if (isPrefill && preset.cityCode) {
                            citySelect.value = preset.cityCode;
                            citySelect.dispatchEvent(new Event('change'));
                        }
                    } else {
                        state.hasProvinces = true;
                        if (isPrefill && preset.provinceCode) {
                            provinceSelect.value = preset.provinceCode;
                            provinceSelect.dispatchEvent(new Event('change'));
                        }
                    }
                } catch (error) {
                    state.hasProvinces = true;
                    resetSelect(provinceSelect, 'Unable to load provinces');
                }

                updateOutput();
            });

            provinceSelect.addEventListener('change', async function() {
                state.provinceName = this.options[this.selectedIndex]?.text || '';
                state.cityName = '';
                state.barangayName = '';
                outputInput.value = '';

                resetSelect(citySelect, 'Select City/Municipality');
                resetSelect(barangaySelect, 'Select Barangay');

                if (!this.value) {
                    updateOutput();
                    return;
                }

                try {
                    await loadCitiesByProvince(this.value);
                    if (isPrefill && preset.cityCode) {
                        citySelect.value = preset.cityCode;
                        citySelect.dispatchEvent(new Event('change'));
                    }
                } catch (error) {
                    resetSelect(citySelect, 'Unable to load cities');
                }

                updateOutput();
            });

            citySelect.addEventListener('change', async function() {
                const selectedCityCode = this.value;
                const selectedCityName = this.options[this.selectedIndex]?.text || '';
                
                // Reset barangay state
                state.cityName = selectedCityName;
                state.barangayName = '';
                outputInput.value = '';

                // Clear and disable barangay dropdown immediately
                resetSelect(barangaySelect, 'Select Barangay');

                if (!selectedCityCode) {
                    updateOutput();
                    return;
                }

                // Load barangays only for the selected city
                await loadBarangays(selectedCityCode);
                
                // If pre-filling and we have a barangay code, try to select it
                if (isPrefill && preset.barangayCode) {
                    // Check if the barangay code exists in the loaded barangays
                    const barangayOption = Array.from(barangaySelect.options).find(opt => opt.value === preset.barangayCode);
                    if (barangayOption) {
                        barangaySelect.value = preset.barangayCode;
                        barangaySelect.dispatchEvent(new Event('change'));
                    }
                }

                updateOutput();
            });

            barangaySelect.addEventListener('change', function() {
                state.barangayName = this.options[this.selectedIndex]?.text || '';
                updateOutput();
                if (isPrefill) {
                    isPrefill = false;
                }
            });

            resetSelect(provinceSelect, 'Select Province');
            resetSelect(citySelect, 'Select City/Municipality');
            resetSelect(barangaySelect, 'Select Barangay');
            loadRegions();
        }
        
        function selectRole(role, event) {
            const roleErr = document.getElementById('role-select-error');
            if (roleErr) { roleErr.textContent = ''; roleErr.style.display = 'none'; }
            document.getElementById('selectedRole').value = role;
            document.getElementById('registrationForm').style.display = 'block';
            document.getElementById('accountFields').style.display = 'block';
            
            // Hide all forms first and disable required attributes
            const employeeForm = document.getElementById('employeeForm');
            const employerForm = document.getElementById('employerForm');
            
            employeeForm.style.display = 'none';
            employerForm.style.display = 'none';
            
            // Remove required from all form fields
            employeeForm.querySelectorAll('[required]').forEach(field => {
                field.removeAttribute('required');
            });
            employerForm.querySelectorAll('[required]').forEach(field => {
                field.removeAttribute('required');
            });
            
            // Remove required from account fields (email, username, password, confirm_password)
            document.querySelectorAll('input[name="email"], input[name="username"], input[name="password"], input[name="confirm_password"]').forEach(field => {
                field.removeAttribute('required');
            });
            
            // Show selected form and enable required attributes
            if (role === 'employee') {
                employeeForm.style.display = 'block';
                // Add required back to employee fields
                employeeForm.querySelectorAll('input[name="first_name"], input[name="last_name"], select[name="sex"], input[name="date_of_birth"], input[name="contact_no"], select[name="civil_status"], select[name="highest_education"], select[name="experience_level"], select[name="preferred_job_type"], select[name="preferred_salary_range"], select[name="employee_region"], select[name="employee_province"], select[name="employee_city"], select[name="employee_barangay"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Add required to account fields for employees (including email)
                document.querySelectorAll('input[name="email"], input[name="username"], input[name="password"], input[name="confirm_password"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Show email field for employees
                const emailFields = document.querySelectorAll('.employee-only');
                emailFields.forEach(field => {
                    field.style.display = 'block';
                });
            } else if (role === 'employer') {
                employerForm.style.display = 'block';
                // Add required back to employer fields
                employerForm.querySelectorAll('input[name="company_name"], input[name="contact_number"], input[name="contact_email"], select[name="employer_region"], select[name="employer_province"], select[name="employer_city"], select[name="employer_barangay"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Add required to account fields for employers (no email)
                document.querySelectorAll('input[name="username"], input[name="password"], input[name="confirm_password"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Hide email field for employers (they use contact_email)
                const emailFields = document.querySelectorAll('.employee-only');
                emailFields.forEach(field => {
                    field.style.display = 'none';
                    // Ensure email field doesn't have required when hidden
                    if (field.name === 'email') {
                        field.removeAttribute('required');
                    }
                });
            }
            
            // Highlight selected card
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('border-primary');
            });
            const selectedCard = event && event.currentTarget
                ? event.currentTarget
                : document.querySelector(`.role-card[data-role="${role}"]`);
            if (selectedCard) {
                selectedCard.classList.add('border-primary');
            }
        }
        
        function resetForm() {
            document.getElementById('registrationForm').style.display = 'none';
            document.getElementById('registrationForm').reset();
            clearFormValidation();
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('border-primary');
            });
        }
        
        function clearFormValidation() {
            document.querySelectorAll('#registrationForm .is-invalid, #registrationForm .form-check-input.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('[id$="-error"]').forEach(el => {
                el.textContent = '';
            });
            const roleErr = document.getElementById('role-select-error');
            if (roleErr) {
                roleErr.textContent = '';
                roleErr.style.display = 'none';
            }
        }

        function markInvalid(fieldId, message) {
            if (!fieldId || !message) return;
            const field = document.getElementById(fieldId);
            if (!field) return;
            const errEl = document.getElementById(fieldId + '-error');
            if (!errEl) return;
            field.classList.add('is-invalid');
            errEl.textContent = message;
        }

        function validateForm(event) {
            // Prevent form submission if validation fails
            if (event) {
                event.preventDefault();
            }
            
            // Get selected role
            const selectedRole = document.getElementById('selectedRole').value;
            const employeeForm = document.getElementById('employeeForm');
            const employerForm = document.getElementById('employerForm');
            
            clearFormValidation();
            let hasError = false;

            if (!selectedRole) {
                const roleErr = document.getElementById('role-select-error');
                if (roleErr) {
                    roleErr.textContent = 'Please select a role to continue';
                    roleErr.style.display = 'block';
                }
                return false;
            }
            
            if (!document.getElementById('terms_agreement').checked) {
                markInvalid('terms_agreement', 'Agree to the Terms and Conditions and Privacy Policy');
                hasError = true;
            }
            
            const username = document.getElementById('username').value.trim();
            if (!username) {
                markInvalid('username', 'Username is required');
                hasError = true;
            } else if (!/^[A-Za-z]+$/.test(username)) {
                markInvalid('username', 'Username must contain only letters (A-Z, a-z)');
                hasError = true;
            }
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!password) {
                markInvalid('password', 'Password is required');
                hasError = true;
            } else {
                if (password !== confirmPassword) {
                    markInvalid('confirm_password', 'Confirm Password must match');
                    hasError = true;
                }
                if (!/[A-Z]/.test(password)) {
                    markInvalid('password', 'Password must contain an uppercase letter');
                    hasError = true;
                } else if (!/[a-z]/.test(password)) {
                    markInvalid('password', 'Password must contain a lowercase letter');
                    hasError = true;
                } else if (!/[0-9]/.test(password)) {
                    markInvalid('password', 'Password must contain a number');
                    hasError = true;
                } else if (!/[@#$%&]/.test(password)) {
                    markInvalid('password', 'Password must contain a special character (@ # $ % &)');
                    hasError = true;
                } else {
                    const evaluation = evaluatePassword(password);
                    if (!evaluation.allRequired) {
                        markInvalid('password', 'Password strength must be Strong');
                        hasError = true;
                    }
                }
            }
            
            if (selectedRole === 'employee') {
                if (!document.getElementById('first_name').value.trim()) {
                    markInvalid('first_name', 'First Name is required');
                    hasError = true;
                }
                if (!document.getElementById('last_name').value.trim()) {
                    markInvalid('last_name', 'Last Name is required');
                    hasError = true;
                }
                if (!document.getElementById('sex').value) {
                    markInvalid('sex', 'Sex/Gender is required');
                    hasError = true;
                }
                if (!document.getElementById('date_of_birth').value) {
                    markInvalid('date_of_birth', 'Date of Birth is required');
                    hasError = true;
                }
                const contactNo = document.getElementById('contact_no').value.trim();
                if (!contactNo) {
                    markInvalid('contact_no', 'Contact Number is required');
                    hasError = true;
                } else if (!contactNo.match(/^09\d{9}$/)) {
                    markInvalid('contact_no', 'Contact number must start with 09 and be 11 digits long');
                    hasError = true;
                }
                if (!document.getElementById('civil_status').value) {
                    markInvalid('civil_status', 'Civil Status is required');
                    hasError = true;
                }
                if (!document.getElementById('highest_education').value) {
                    markInvalid('highest_education', 'Highest Education is required');
                    hasError = true;
                }
                if (!document.getElementById('location').value.trim()) {
                    markInvalid('employee_region', 'Location is required');
                    hasError = true;
                }
                if (!document.getElementById('experience_level').value) {
                    markInvalid('experience_level', 'Experience Level is required');
                    hasError = true;
                }
                if (!document.getElementById('preferred_job_type').value) {
                    markInvalid('preferred_job_type', 'Preferred Job Type is required');
                    hasError = true;
                }
                if (!document.getElementById('preferred_salary_range').value) {
                    markInvalid('preferred_salary_range', 'Preferred Salary Range is required');
                    hasError = true;
                }
                
                const email = document.getElementById('email').value.trim();
                if (!email) {
                    markInvalid('email', 'Email is required');
                    hasError = true;
                } else if (!email.endsWith('@gmail.com')) {
                    markInvalid('email', 'Email must be a valid Gmail address (@gmail.com)');
                    hasError = true;
                }
                
                const firstName = document.getElementById('first_name').value.trim();
                const lastName = document.getElementById('last_name').value.trim();
                if (firstName && !/^[a-zA-Z\s\-\'\.]+$/.test(firstName)) {
                    markInvalid('first_name', 'First name can only contain letters, spaces, hyphens, apostrophes, and periods');
                    hasError = true;
                }
                if (lastName && !/^[a-zA-Z\s\-\'\.]+$/.test(lastName)) {
                    markInvalid('last_name', 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods');
                    hasError = true;
                }
            } else if (selectedRole === 'employer') {
                if (!document.getElementById('company_name').value.trim()) {
                    markInvalid('company_name', 'Company Name is required');
                    hasError = true;
                }
                const contactNumber = document.getElementById('contact_number').value.trim();
                if (!contactNumber) {
                    markInvalid('contact_number', 'Contact Number is required');
                    hasError = true;
                } else if (!contactNumber.match(/^09\d{9}$/)) {
                    markInvalid('contact_number', 'Contact number must start with 09 and be 11 digits long');
                    hasError = true;
                }
                const contactEmail = document.getElementById('contact_email').value.trim();
                if (!contactEmail) {
                    markInvalid('contact_email', 'Contact Email is required');
                    hasError = true;
                } else if (!contactEmail.endsWith('@gmail.com')) {
                    markInvalid('contact_email', 'Contact Email must be a valid Gmail address (@gmail.com)');
                    hasError = true;
                }
                if (!document.getElementById('contact_first_name').value.trim()) {
                    markInvalid('contact_first_name', 'Contact First Name is required');
                    hasError = true;
                }
                if (!document.getElementById('contact_last_name').value.trim()) {
                    markInvalid('contact_last_name', 'Contact Last Name is required');
                    hasError = true;
                }
                if (!document.getElementById('contact_position').value.trim()) {
                    markInvalid('contact_position', 'Contact Position is required');
                    hasError = true;
                }
                if (!document.getElementById('location_address').value.trim()) {
                    markInvalid('employer_region', 'Location Address is required');
                    hasError = true;
                }
            }
            
            if (hasError) return false;
            
            // If validation passed, submit the form
            document.getElementById('registrationForm').submit();
            return false;
        }
        
        // Style role cards and initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            // Remove all required attributes initially
            document.querySelectorAll('#registrationForm [required]').forEach(field => {
                field.removeAttribute('required');
            });
            
            // Ensure email field is hidden and not required initially
            const emailFields = document.querySelectorAll('.employee-only');
            emailFields.forEach(field => {
                field.style.display = 'none';
                field.removeAttribute('required');
            });
            
            // Setup contact number input masks
            setupContactNumberMask();

            // Auto uppercase text inputs (except password)
            setupAutoUppercaseInputs();

            // Allow only letters for username
            setupUsernameLettersOnly();

            // Initialize skills selection state
            initializeSkillsSelect();

            initPhilippinesAddress({
                regionSelect: document.getElementById('employee_region'),
                provinceSelect: document.getElementById('employee_province'),
                citySelect: document.getElementById('employee_city'),
                barangaySelect: document.getElementById('employee_barangay'),
                outputInput: document.getElementById('location')
            });

            initPhilippinesAddress({
                regionSelect: document.getElementById('employer_region'),
                provinceSelect: document.getElementById('employer_province'),
                citySelect: document.getElementById('employer_city'),
                barangaySelect: document.getElementById('employer_barangay'),
                outputInput: document.getElementById('location_address')
            });

            const preselectedRole = document.getElementById('selectedRole').value;
            if (preselectedRole) {
                selectRole(preselectedRole);
            }
            
            const roleCards = document.querySelectorAll('.role-card');
            roleCards.forEach(card => {
                card.style.cursor = 'pointer';
                card.style.transition = 'all 0.3s ease';
                
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.08)';
                });
            });
        });
    </script>
</body>
</html>
