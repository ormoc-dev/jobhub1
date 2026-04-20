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
<html lang="en" class="auth-page">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        :root {
            --auth-bg: #f1f5f9;
            --auth-card: #ffffff;
            --auth-border: #e2e8f0;
            --auth-text: #0f172a;
            --auth-muted: #64748b;
            --auth-primary: #2563eb;
            --auth-primary-hover: #1d4ed8;
            --auth-aside-bg: #f0f6ff;
            --auth-aside-text: #0f172a;
            --auth-aside-muted: #64748b;
            --auth-aside-border: #dbeafe;
        }

        html.auth-page {
            height: 100%;
            overflow: hidden;
        }

        body.auth-page {
            margin: 0;
            padding-top: 0 !important;
            min-height: 100%;
            height: 100%;
            overflow: hidden;
            background: var(--auth-bg);
            color: var(--auth-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .login-wrapper {
            height: 100%;
            max-height: 100vh;
            max-height: 100dvh;
            padding: clamp(0.75rem, 2vmin, 1.5rem) 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            overflow: hidden;
        }

        .login-wrapper > .container {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }

        .login-shell {
            background: var(--auth-card);
            border-radius: 16px;
            border: 1px solid var(--auth-border);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 12px 40px rgba(15, 23, 42, 0.06);
            max-width: 1040px;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            flex: 0 1 auto;
            min-height: 0;
            max-height: min(100%, calc(100vh - 1.5rem));
            max-height: min(100%, calc(100dvh - 1.5rem));
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        .login-shell > .row {
            min-height: 0;
        }

        .login-shell .login-card {
            padding: clamp(1.75rem, 4vw, 2.75rem);
            width: 100%;
            max-width: 420px;
        }

        .auth-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.75rem;
        }

        .auth-brand-logo {
            width: auto;
            max-width: min(220px, 100%);
            max-height: 72px;
            height: auto;
            display: block;
            object-fit: contain;
            object-position: center;
        }

        .auth-headline {
            font-size: clamp(1.5rem, 2.5vw, 1.75rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--auth-text);
            margin-bottom: 0.35rem;
        }

        .auth-sub {
            font-size: 0.9375rem;
            color: var(--auth-muted);
            margin-bottom: 0;
            line-height: 1.5;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #334155;
            margin-bottom: 0.35rem;
        }

        .auth-page .input-group-text {
            background: #f8fafc;
            border-color: var(--auth-border);
            color: #64748b;
        }

        .auth-page .form-control {
            border-color: var(--auth-border);
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
        }

        .auth-page .form-control:focus {
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .auth-page .input-group .btn-outline-secondary {
            border-color: var(--auth-border);
            color: #64748b;
        }

        .auth-page .input-group .btn-outline-secondary:hover {
            background: #f8fafc;
            color: #334155;
        }

        .auth-page .btn-primary {
            background: var(--auth-primary);
            border: none;
            font-weight: 600;
            padding: 0.65rem 1rem;
            border-radius: 10px;
            box-shadow: none;
            transition: background-color 0.15s ease;
        }

        .auth-page .btn-primary:hover {
            background: var(--auth-primary-hover);
            transform: none;
        }

        .auth-page .btn-primary:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.35);
        }

        .auth-hint {
            font-size: 0.8125rem;
            color: var(--auth-muted);
            margin-top: 0.35rem;
        }

        .auth-meta-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .auth-page .form-check-label {
            font-size: 0.875rem;
            color: #475569;
        }

        .auth-page .text-muted a {
            color: var(--auth-primary) !important;
            font-weight: 500;
            text-decoration: none;
        }

        .auth-page .text-muted a:hover {
            text-decoration: underline;
        }

        .auth-divider {
            height: 1px;
            background: var(--auth-border);
            margin: 1.5rem 0;
        }

        .auth-footer-links {
            font-size: 0.875rem;
        }

        .login-side {
            background: var(--auth-aside-bg);
            color: var(--auth-aside-text);
            padding: clamp(2rem, 5vw, 3rem);
            min-height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid var(--auth-aside-border);
        }

        .login-side-eyebrow {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--auth-primary);
            margin-bottom: 1rem;
        }

        .login-side-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1.5rem;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid var(--auth-aside-border);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--auth-primary);
            font-size: 2rem;
        }

        .login-side h3 {
            font-size: clamp(1.35rem, 2.5vw, 1.6rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.75rem;
            color: var(--auth-aside-text);
        }

        .login-side .lead {
            font-size: 1rem;
            color: var(--auth-aside-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .login-stat-row {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .login-stat {
            flex: 1 1 90px;
            max-width: 120px;
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem 0.75rem;
            border: 1px solid var(--auth-border);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .login-stat h4 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.15rem;
            color: var(--auth-aside-text);
        }

        .login-stat small {
            font-size: 0.75rem;
            color: var(--auth-aside-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .login-side-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--auth-aside-border);
            font-size: 0.8125rem;
            color: var(--auth-aside-muted);
        }

        @media (max-width: 991.98px) {
            .login-shell {
                border-radius: 12px;
            }
        }

        @media (max-height: 760px) {
            .login-shell .login-card {
                padding: 1.15rem 1.25rem;
            }

            .auth-brand {
                margin-bottom: 1rem;
            }

            .auth-brand-logo {
                max-width: 200px;
                max-height: 60px;
            }

            .login-side {
                padding: 1.25rem 1.5rem;
            }

            .login-side-icon {
                width: 56px;
                height: 56px;
                margin-bottom: 1rem;
                font-size: 1.5rem;
            }

            .login-side .lead {
                margin-bottom: 1.25rem;
            }

            .login-side-footer {
                margin-top: 1.25rem;
                padding-top: 1rem;
            }

            .auth-divider {
                margin: 1rem 0;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="container-fluid login-wrapper">
        <div class="container login-shell">
            <div class="row g-0 align-items-stretch">
                <!-- Left: form (single inner wrapper) -->
                <div class="col-lg-6 d-flex align-items-center justify-content-center px-3 px-lg-4">
                    <div class="login-card">
                        <div class="text-center mb-4">
                            <div class="auth-brand">
                                <img src="images/LOGO.png" alt="WORKLINK Job Seeker System" class="auth-brand-logo" decoding="async">
                            </div>
                            <h1 class="auth-headline">Welcome back</h1>
                            <p class="auth-sub">Sign in to unlock your next opportunity</p>
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
                            <p class="auth-hint mb-0">
                                <i class="fas fa-info-circle me-1 opacity-75"></i>
                                Use your username or email (e.g. example@gmail.com)
                            </p>
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

                        <div class="auth-meta-row">
                            <div class="form-check mb-0">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot_password.php" class="text-muted small">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3" <?php echo $disabledAttr; ?>>
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </form>
                        <div class="auth-divider"></div>
                        <div class="text-center auth-footer-links">
                            <p class="text-muted mb-2">Don’t have an account?
                                <a href="register.php">Create one</a>
                            </p>
                            <a href="index.php" class="text-muted">
                                <i class="fas fa-arrow-left me-1"></i>Back to home
                            </a>
                        </div>
                    </div>
                </div>

            <!-- Right: promo panel -->
                <div class="col-lg-6 d-none d-lg-flex">
                    <div class="login-side text-center w-100">
                        <span class="login-side-eyebrow">Trusted by teams</span>
                        <div class="login-side-icon" aria-hidden="true">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3>Your next role starts here</h3>
                        <p class="lead">Discover opportunities that match your skills, goals, and schedule—all in one place.</p>
                        <div class="login-stat-row">
                            <div class="login-stat">
                                <h4>1000+</h4>
                                <small>Jobs</small>
                            </div>
                            <div class="login-stat">
                                <h4>500+</h4>
                                <small>Companies</small>
                            </div>
                            <div class="login-stat">
                                <h4>50+</h4>
                                <small>Categories</small>
                            </div>
                        </div>
                        <div class="login-side-footer">
                            Secure login · Fast hiring · Smart matching
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
