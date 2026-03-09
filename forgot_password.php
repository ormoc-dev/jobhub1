<?php
include 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id, status FROM users WHERE (username = ? OR email = ?) AND email = ?");
        $stmt->execute([$username, $username, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No matching account found. Please check your details.';
        } elseif ($user['status'] === 'suspended') {
            $error = 'Your account is suspended. Please contact support.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);
            $success = 'Password updated successfully. You can now sign in.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        .forgot-body {
            background: radial-gradient(circle at top, #eef2ff 0%, #f8fafc 45%, #ffffff 100%);
            min-height: 100vh;
        }
        .forgot-shell {
            min-height: 100vh;
            padding: 24px;
        }
        .forgot-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .forgot-panel {
            padding: 40px;
        }
        .forgot-brand {
            color: #0f172a;
        }
        .forgot-subtitle {
            color: #64748b;
        }
        .forgot-accent {
            color: #2563eb;
        }
        .forgot-input-group .input-group-text {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .forgot-input-group .form-control {
            border: 1px solid #e2e8f0;
        }
        .forgot-input-group .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }
        .forgot-action-btn {
            background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%);
            border: none;
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 0.2px;
            padding: 12px 16px;
        }
        .forgot-action-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #0284c7 100%);
        }
        .forgot-link {
            color: #2563eb;
            text-decoration: none;
        }
        .forgot-link:hover {
            color: #1d4ed8;
        }
        .forgot-info {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #e2e8f0;
        }
        .forgot-info i {
            color: #38bdf8;
        }
        @media (max-width: 991px) {
            .forgot-panel {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body class="forgot-body">
    <div class="container forgot-shell d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-xl-9 col-lg-11">
                <div class="forgot-card">
                    <div class="row g-0">
                        <div class="col-lg-6">
                            <div class="forgot-panel">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold forgot-brand">
                            <img src="worklink.jpg" alt="WORKLINK" class="logo-img me-2" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover;">
                            <span class="forgot-accent">WORK</span>LINK
                        </h2>
                        <p class="forgot-subtitle mb-0">
                        Reset your password safely and get back to your account.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group forgot-input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username"
                                       placeholder="Your username"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Registered Email</label>
                            <div class="input-group forgot-input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="example@gmail.com"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group forgot-input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group forgot-input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn forgot-action-btn w-100 mb-3">
                            <i class="fas fa-key me-2"></i>Update Password
                        </button>
                    </form>

                    <div class="text-center">
                        <a href="login.php" class="forgot-link">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                            </div>
                        </div>
                        <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center forgot-info">
                            <div class="text-center p-5">
                                <i class="fas fa-shield-alt fa-5x mb-4"></i>
                                <h3 class="fw-bold mb-3">Secure Account Recovery</h3>
                                <p class="lead mb-4">Update your password and get back to WORKLINK</p>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="fw-bold">Fast</h4>
                                        <small>Reset</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="fw-bold">Safe</h4>
                                        <small>Access</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="fw-bold">Easy</h4>
                                        <small>Steps</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(buttonId, inputId) {
            const button = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            button.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        togglePassword('toggleNewPassword', 'new_password');
        togglePassword('toggleConfirmPassword', 'confirm_password');
    </script>
</body>
</html>
