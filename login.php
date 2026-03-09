<?php
include 'config.php';

$error = '';
$success = '';
$isLocked = false;
$lockRemainingSeconds = 0;
$maxAttempts = 3;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Check if IP is locked
        $stmt = $pdo->prepare("SELECT locked_until FROM login_attempts WHERE ip_address = ? AND username = ? AND locked_until > NOW() ORDER BY last_attempt DESC LIMIT 1");
        $stmt->execute([$ip_address, $username]);
        $lockInfo = $stmt->fetch();
        
        if ($lockInfo) {
            $isLocked = true;
            $lockedUntil = new DateTime($lockInfo['locked_until']);
            $now = new DateTime();
            $lockRemainingSeconds = max(0, $lockedUntil->getTimestamp() - $now->getTimestamp());
            $error = 'Too many failed login attempts. Please try again in 5 minutes.';
        } else {
            // Check user credentials
            $stmt = $pdo->prepare("SELECT id, username, password, role, status FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    // Clear any failed attempts on successful login
                    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                    $stmt->execute([$ip_address]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Update user activity
                    updateUserActivity($user['id']);
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            redirect('admin/dashboard.php');
                            break;
                        case 'employee':
                            redirect('employee/dashboard.php');
                            break;
                        case 'employer':
                            redirect('employer/dashboard.php');
                            break;
                        default:
                            redirect('index.php');
                    }
                } elseif ($user['status'] === 'pending') {
                    $error = 'Your account is pending approval. Please wait for admin approval.';
                } else {
                    $error = 'Your account has been suspended. Please contact support.';
                }
            } else {
                // Record failed login attempt
                $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ? AND username = ?");
                $stmt->execute([$ip_address, $username]);
                $attempt = $stmt->fetch();
                
                if ($attempt) {
                    $newAttempts = $attempt['attempts'] + 1;
                    if ($newAttempts >= $maxAttempts) {
                        // Lock for 5 minutes
                        $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE ip_address = ? AND username = ?");
                        $stmt->execute([$newAttempts, $ip_address, $username]);
                        $isLocked = true;
                        $lockRemainingSeconds = 300;
                        $error = 'Too many failed login attempts. Please try again in 5 minutes.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE ip_address = ? AND username = ?");
                        $stmt->execute([$newAttempts, $ip_address, $username]);
                        $attemptsRemaining = $maxAttempts - $newAttempts;
                        $error = 'Invalid username or password. Attempts remaining: ' . $attemptsRemaining;
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempts) VALUES (?, ?, 1)");
                    $stmt->execute([$ip_address, $username]);
                    $attemptsRemaining = $maxAttempts - 1;
                    $error = 'Invalid username or password. Attempts remaining: ' . $attemptsRemaining;
                }
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
    <title>Login - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top, #f5f3ff 0%, #ecfeff 45%, #eef2ff 100%);
            color: #0f172a;
        }
        .login-wrapper {
            min-height: 100vh;
            padding: 40px 16px;
            display: flex;
            align-items: center;
        }
        .login-shell {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            box-shadow: 0 28px 70px rgba(30, 41, 59, 0.18);
            border: 1px solid rgba(148, 163, 184, 0.25);
            overflow: hidden;
        }
        .login-card {
            background: #ffffff;
            padding: 36px 34px;
        }
        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
            border-radius: 999px;
            font-weight: 600;
        }
        .brand-pill img {
            height: 34px;
            width: 34px;
            border-radius: 50%;
            object-fit: cover;
        }
        .form-label {
            font-weight: 600;
            color: #111827;
        }
        .input-group-text {
            background: #eef2ff;
            border-color: #e0e7ff;
            color: #4f46e5;
        }
        .form-control {
            border-color: #e0e7ff;
        }
        .form-control:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 0.2rem rgba(129, 140, 248, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #22d3ee 100%);
            border: none;
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(34, 211, 238, 0.35);
        }
        .login-side {
            background: linear-gradient(145deg, #312e81 0%, #4338ca 45%, #0ea5e9 100%);
            color: #ffffff;
            padding: 48px 44px;
            height: 100%;
        }
        .login-stat {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 14px 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .text-muted a {
            color: #4f46e5 !important;
        }
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 24px 0;
        }
        .login-side .badge {
            background: rgba(255, 255, 255, 0.9) !important;
            color: #312e81 !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid login-wrapper">
        <div class="container login-shell">
            <div class="row g-0 align-items-stretch">
                <!-- Left Side - Login Form -->
                <div class="col-lg-6 d-flex align-items-center justify-content-center">
                    <div class="form-container w-100" style="max-width: 440px;">
                        <div class="login-card">
                            <div class="text-center mb-4">
                                <span class="brand-pill">
                                    <img src="worklink.jpg" alt="WORKLINK">
                                    WORKLINK
                                </span>
                                <h2 class="fw-bold mt-3 mb-1 text-dark">Welcome back</h2>
                                <p class="text-muted mb-0">Sign in to unlock your next opportunity</p>
                            </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" <?php echo (strpos($error, '5 minutes') !== false) ? 'id="lockout-alert"' : ''; ?>>
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            <?php if (strpos($error, '5 minutes') !== false): ?>
                                <div id="countdown-timer" class="mt-2">
                                    <small>Time remaining: <span id="countdown" data-remaining="<?php echo (int) $lockRemainingSeconds; ?>">5:00</span></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php $disabledAttr = $isLocked ? 'disabled' : ''; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="example@gmail.com" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required <?php echo $disabledAttr; ?>>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Enter your username or email address (e.g., example@gmail.com)
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required <?php echo $disabledAttr; ?>>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password while pressed" <?php echo $disabledAttr; ?>>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        <div class="mb-3 text-end">
                            <a href="forgot_password.php" class="text-muted">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3" <?php echo $disabledAttr; ?>>
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </form>
                    <div class="divider"></div>
                    <div class="text-center">
                        <p class="text-muted mb-2">Don't have an account? 
                            <a href="register.php" class="text-primary">Sign up here</a>
                        </p>
                        <a href="index.php" class="text-muted">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                        </div>
                    </div>
                </div>

            <!-- Right Side - Information -->
                <div class="col-lg-6 d-none d-lg-flex">
                    <div class="login-side text-center d-flex flex-column justify-content-center w-100">
                        <div class="mb-4">
                            <span class="badge rounded-pill px-3 py-2">Trusted by teams</span>
                        </div>
                        <i class="fas fa-users fa-5x mb-4"></i>
                        <h3 class="fw-bold mb-3">Welcome Back to WORKLINK</h3>
                        <p class="lead mb-4">Discover roles that match your skills and goals</p>
                        <div class="row text-center g-3">
                            <div class="col-4">
                                <div class="login-stat">
                                    <h4 class="fw-bold mb-1">1000+</h4>
                                    <small>Jobs</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="login-stat">
                                    <h4 class="fw-bold mb-1">500+</h4>
                                    <small>Companies</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="login-stat">
                                    <h4 class="fw-bold mb-1">50+</h4>
                                    <small>Categories</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <small class="opacity-75">Secure login • Fast hiring • Smart matching</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show password only while button is pressed
        const toggleButton = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = toggleButton.querySelector('i');

        function showPassword() {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        }

        function hidePassword() {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }

        toggleButton.addEventListener('mousedown', showPassword);
        toggleButton.addEventListener('touchstart', showPassword);
        toggleButton.addEventListener('mouseup', hidePassword);
        toggleButton.addEventListener('mouseleave', hidePassword);
        toggleButton.addEventListener('touchend', hidePassword);
        toggleButton.addEventListener('touchcancel', hidePassword);

        // Countdown timer for login lockout
        function clearLockoutAlert() {
            const alert = document.getElementById('lockout-alert');
            if (alert) {
                alert.style.display = 'none';
            }
        }

        function enableLoginFields() {
            const fields = [
                document.getElementById('username'),
                document.getElementById('password'),
                document.getElementById('togglePassword'),
                document.querySelector('button[type="submit"]')
            ];
            fields.forEach(function(field) {
                if (field) {
                    field.removeAttribute('disabled');
                }
            });
        }

        function startCountdown() {
            const countdownElement = document.getElementById('countdown');
            if (!countdownElement) return;
            const remaining = parseInt(countdownElement.getAttribute('data-remaining') || '300', 10);
            let timeLeft = Number.isNaN(remaining) ? 300 : remaining;
            
            const timer = setInterval(function() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                countdownElement.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    countdownElement.textContent = '0:00';
                    enableLoginFields();
                    clearLockoutAlert();
                }
                
                timeLeft--;
            }, 1000);
        }

        // Start countdown if lockout message is shown
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('countdown-timer')) {
                startCountdown();
            }
        });
    </script>
</body>
</html>
