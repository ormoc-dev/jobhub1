<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jobhub1');

// Site Configuration
define('SITE_NAME', 'WORKLINK');
define('SITE_URL', 'http://localhost/jobhub1/');
define('UPLOAD_PATH', 'uploads/');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Session configuration
session_start();

// Helper functions
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        redirect('index.php');
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function uploadFile($file, $folder = '') {
    // Get absolute path to project root where config.php is located
    $documentRoot = dirname(__FILE__); // config.php is in project root
    $uploadDir = $documentRoot . '/' . UPLOAD_PATH . $folder . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validate file size (10MB max)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 10MB limit.');
    }
    
    // Validate file type
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'bmp', 'webp'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }
    
    $fileName = time() . '_' . str_replace(' ', '_', basename($file['name']));
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Return relative path for web access instead of absolute path
        return UPLOAD_PATH . $folder . '/' . $fileName;
    }
    return false;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

function updateUserActivity($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // Log error but don't break the login process
        error_log("Failed to update user activity: " . $e->getMessage());
    }
}

// Function to detect device type (Mobile or Laptop/PC)
function detectDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone', 'Opera Mini'];
    
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return 'Mobile';
        }
    }
    return 'Laptop';
}

// Function to detect browser name
function detectBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (stripos($userAgent, 'Chrome') !== false && stripos($userAgent, 'Edg') === false) {
        return 'Chrome';
    } elseif (stripos($userAgent, 'Firefox') !== false) {
        return 'Firefox';
    } elseif (stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) {
        return 'Safari';
    } elseif (stripos($userAgent, 'Edg') !== false) {
        return 'Edge';
    } elseif (stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR') !== false) {
        return 'Opera';
    } else {
        return 'Unknown';
    }
}

// Function to get real IP address (handles proxies and load balancers)
function getRealIPAddress() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Function to get location from IP using geolocation API
function getLocationFromIP($ip) {
    // Clean the IP address
    $ip = trim($ip);
    
    // For localhost/development, return a default location
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.16.') === 0) {
        return 'Quezon City, Philippines';
    }
    
    // Validate IP address
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'Manila, Philippines';
    }
    
    // Check cache first (store in session to avoid repeated API calls)
    $cacheKey = 'ip_location_' . md5($ip);
    if (isset($_SESSION[$cacheKey])) {
        return $_SESSION[$cacheKey];
    }
    
    // Use ip-api.com (free, no API key required for basic usage)
    // Get complete location details: city, region, country, timezone, isp
    $url = "http://ip-api.com/json/{$ip}?fields=status,message,city,regionName,country,countryCode,timezone,isp";
    
    // Try to get location using cURL first, then fallback to file_get_contents
    $response = false;
    $httpCode = 0;
    
    if (function_exists('curl_init')) {
        // Use cURL if available
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // If cURL failed, try file_get_contents as fallback
        if ($response === false || $httpCode !== 200) {
            $response = false;
        }
    }
    
    // Fallback to file_get_contents if cURL failed or not available
    if ($response === false && ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'ignore_errors' => true
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $httpCode = 200;
        }
    }
    
    // If API call successful, parse the response
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            $locationParts = [];
            
            // Build complete location string with all available information
            // Format: City, Region, Country
            
            // Add city if available
            if (!empty($data['city'])) {
                $locationParts[] = $data['city'];
            }
            
            // Add region/state if available (and different from city)
            if (!empty($data['regionName']) && $data['regionName'] !== $data['city']) {
                $locationParts[] = $data['regionName'];
            }
            
            // Always add country for complete location
            if (!empty($data['country'])) {
                $locationParts[] = $data['country'];
            }
            
            // If we have at least city and country, return the complete location
            if (!empty($locationParts) && count($locationParts) >= 2) {
                $location = implode(', ', $locationParts);
                // Cache the result
                $_SESSION[$cacheKey] = $location;
                return $location;
            }
            
            // If only country is available, return it
            if (!empty($data['country'])) {
                $location = $data['country'];
                $_SESSION[$cacheKey] = $location;
                return $location;
            }
        }
    }
    
    // Fallback: Try alternative method using ipapi.co
    $url2 = "https://ipapi.co/{$ip}/json/";
    $response2 = false;
    $httpCode2 = 0;
    
    if (function_exists('curl_init')) {
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url2);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response2 = @curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        if ($response2 === false || $httpCode2 !== 200) {
            $response2 = false;
        }
    }
    
    // Fallback to file_get_contents for ipapi.co
    if ($response2 === false && ini_get('allow_url_fopen')) {
        $context2 = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'ignore_errors' => true
            ]
        ]);
        $response2 = @file_get_contents($url2, false, $context2);
        if ($response2 !== false) {
            $httpCode2 = 200;
        }
    }
    
    if ($response2 && $httpCode2 === 200) {
        $data2 = json_decode($response2, true);
        
        if (!isset($data2['error'])) {
            $locationParts = [];
            
            // Build complete location from ipapi.co response
            if (!empty($data2['city'])) {
                $locationParts[] = $data2['city'];
            }
            
            // Add region/state if available and different from city
            if (!empty($data2['region']) && $data2['region'] !== $data2['city']) {
                $locationParts[] = $data2['region'];
            }
            
            // Always add country for complete location
            if (!empty($data2['country_name'])) {
                $locationParts[] = $data2['country_name'];
            }
            
            // Return complete location if we have at least city and country
            if (!empty($locationParts) && count($locationParts) >= 2) {
                $location = implode(', ', $locationParts);
                $_SESSION[$cacheKey] = $location;
                return $location;
            }
            
            // If only country is available, return it
            if (!empty($data2['country_name'])) {
                $location = $data2['country_name'];
                $_SESSION[$cacheKey] = $location;
                return $location;
            }
        }
    }
    
    // Final fallback: return default complete location
    $defaultLocation = 'Manila, Metro Manila, Philippines';
    $_SESSION[$cacheKey] = $defaultLocation;
    return $defaultLocation;
}

// Function to record login session
// Note: login_sessions table is defined in sql/all_additional_tables.sql
function recordLoginSession($userId) {
    global $pdo;
    try {
        // Mark old sessions as inactive
        $stmt = $pdo->prepare("UPDATE login_sessions SET is_active = 0 WHERE user_id = ? AND session_id != ?");
        $stmt->execute([$userId, session_id()]);
        
        // Record new login session
        $deviceType = detectDeviceType();
        $browser = detectBrowser();
        $ipAddress = getRealIPAddress();
        $location = getLocationFromIP($ipAddress);
        
        $stmt = $pdo->prepare("INSERT INTO login_sessions (user_id, device_type, browser, location, ip_address, session_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $deviceType, $browser, $location, $ipAddress, session_id()]);
        
        // Update last_activity in users table
        updateUserActivity($userId);
    } catch (Exception $e) {
        // Log error but don't break the login process
        error_log("Failed to record login session: " . $e->getMessage());
    }
}

// Function to update session activity
function updateSessionActivity($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE login_sessions SET last_activity = NOW() WHERE user_id = ? AND session_id = ? AND is_active = 1");
        $stmt->execute([$userId, session_id()]);
        updateUserActivity($userId);
    } catch (Exception $e) {
        error_log("Failed to update session activity: " . $e->getMessage());
    }
}

// Function to get all unique required skills from $jobTitleToSkills (same as post-job.php)
function getAllUniqueSkills() {
    static $cachedSkills = null;
    if ($cachedSkills !== null) {
        return $cachedSkills;
    }
    
    $postJobPath = __DIR__ . '/employer/post-job.php';
    if (!file_exists($postJobPath)) {
        $cachedSkills = [];
        return $cachedSkills;
    }
    
    $content = file_get_contents($postJobPath);
    
    // Extract only the $jobTitleToSkills block (required skills), not $categorySkillOptions etc.
    if (!preg_match('/\$jobTitleToSkills\s*=\s*\[/s', $content, $startMatch, PREG_OFFSET_CAPTURE)) {
        $cachedSkills = [];
        return $cachedSkills;
    }
    $blockStart = $startMatch[0][1];
    if (!preg_match('/\$jobTitleToQualifications\s*=\s*\[/s', $content, $endMatch, PREG_OFFSET_CAPTURE)) {
        $cachedSkills = [];
        return $cachedSkills;
    }
    $blockEnd = $endMatch[0][1];
    $block = substr($content, $blockStart, $blockEnd - $blockStart);
    
    // Match only inner arrays (=> [ 'Skill1', 'Skill2', ... ]) — these are required skills; keys are job titles
    $allSkills = [];
    if (preg_match_all("/=>\s*\[\s*([\s\S]*?)\]\s*(?=\s*,|\s*\];)/", $block, $innerMatches)) {
        foreach ($innerMatches[1] as $innerContent) {
            if (preg_match_all("/'([^']+)'/", $innerContent, $skillMatches) && !empty($skillMatches[1])) {
                foreach ($skillMatches[1] as $skill) {
                    $skill = trim($skill);
                    if ($skill !== '' && strlen($skill) < 80 && !preg_match('/^\/\//', $skill)) {
                        $allSkills[] = $skill;
                    }
                }
            }
        }
    }
    
    $uniqueSkills = array_unique($allSkills);
    sort($uniqueSkills);
    $cachedSkills = array_values($uniqueSkills);
    return $cachedSkills;
}

// 2FA Helper Functions
function generate2FASecret($length = 16) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 characters
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $secret;
}

function base32Decode($secret) {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $secret = strtoupper($secret);
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $val = strpos($base32chars, $secret[$i]);
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    
    $result = '';
    for ($i = 0; $i < strlen($bits) - 7; $i += 8) {
        $result .= chr(bindec(substr($bits, $i, 8)));
    }
    
    return $result;
}

function generateTOTP($secret, $timeStep = 30) {
    $key = base32Decode($secret);
    $time = floor(time() / $timeStep);
    $time = pack('N*', 0) . pack('N*', $time);
    
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function verify2FACode($secret, $code, $timeStep = 30, $window = 1) {
    if (empty($secret) || empty($code)) {
        return false;
    }
    
    // Remove any spaces from the code
    $code = str_replace(' ', '', trim($code));
    
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    
    $time = floor(time() / $timeStep);
    $key = base32Decode($secret);
    
    // Check current time and adjacent time windows
    for ($i = -$window; $i <= $window; $i++) {
        $checkTime = $time + $i;
        
        // Calculate TOTP for this time window
        $timeBytes = pack('N*', 0) . pack('N*', $checkTime);
        $hash = hash_hmac('sha1', $timeBytes, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $calculatedCode = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        $calculatedCode = str_pad($calculatedCode, 6, '0', STR_PAD_LEFT);
        
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

function get2FAQRCodeURL($secret, $email, $issuer = 'WORKLINK') {
    $label = urlencode($email);
    $issuerEncoded = urlencode($issuer);
    $secretEncoded = urlencode($secret);
    
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=otpauth://totp/{$label}?secret={$secretEncoded}&issuer={$issuerEncoded}";
}

// =====================================================
// SUBSCRIPTION SYSTEM FUNCTIONS
// =====================================================

/**
 * Check if user has active premium subscription
 */
function hasActiveSubscription($userId, $planType = 'premium') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ? 
            AND us.status = 'active' 
            AND us.payment_status = 'paid'
            AND (us.end_date IS NULL OR us.end_date > NOW())
            AND sp.plan_type IN ('premium', 'enterprise')
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking subscription: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's active subscription
 */
function getUserSubscription($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT us.*, sp.plan_name, sp.plan_type, sp.role, sp.features, sp.description
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ? 
            AND us.status = 'active' 
            AND us.payment_status = 'paid'
            AND (us.end_date IS NULL OR us.end_date > NOW())
            ORDER BY us.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting subscription: " . $e->getMessage());
        return false;
    }
}

/**
 * Get subscription plans for a specific role
 */
function getSubscriptionPlans($role = 'both') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM subscription_plans 
            WHERE is_active = TRUE 
            AND (role = ? OR role = 'both')
            ORDER BY price ASC
        ");
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting subscription plans: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a new subscription for user
 */
function createSubscription($userId, $planId, $paymentStatus = 'paid', $paymentMethod = 'manual', $transactionId = null) {
    global $pdo;
    try {
        // Get plan details
        $planStmt = $pdo->prepare("SELECT duration_days FROM subscription_plans WHERE id = ?");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        
        if (!$plan) {
            return false;
        }
        
        // Calculate end date
        $endDate = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        
        // Cancel any existing active subscriptions
        $cancelStmt = $pdo->prepare("UPDATE user_subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'");
        $cancelStmt->execute([$userId]);
        
        // Create new subscription
        $stmt = $pdo->prepare("
            INSERT INTO user_subscriptions (user_id, plan_id, status, start_date, end_date, payment_status, payment_method, transaction_id)
            VALUES (?, ?, 'active', NOW(), ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $planId, $endDate, $paymentStatus, $paymentMethod, $transactionId]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating subscription: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark application as fast/priority application
 */
function markFastApplication($applicationId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO fast_applications (application_id, is_priority, priority_score)
            VALUES (?, TRUE, 100)
            ON DUPLICATE KEY UPDATE is_priority = TRUE, priority_score = 100
        ");
        $stmt->execute([$applicationId]);
        return true;
    } catch (Exception $e) {
        error_log("Error marking fast application: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if application is a fast/priority application
 */
function isFastApplication($applicationId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM fast_applications WHERE application_id = ? AND is_priority = TRUE");
        $stmt->execute([$applicationId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking fast application: " . $e->getMessage());
        return false;
    }
}

/**
 * Require subscription for premium features
 */
function requireSubscription($userId, $planType = 'premium', $redirectUrl = null) {
    if (!hasActiveSubscription($userId, $planType)) {
        $role = getUserRole();
        if ($redirectUrl === null) {
            $redirectUrl = $role === 'employer' ? 'employer/subscription.php' : 'employee/subscription.php';
        }
        $_SESSION['error'] = 'This feature requires a premium subscription. Please upgrade your plan.';
        redirect($redirectUrl);
    }
}

/**
 * Check and remove expired jobs that have no applications
 * This function automatically deletes job postings where:
 * - The deadline has passed
 * - No one has applied (application_count = 0)
 */
function cleanupExpiredJobs() {
    global $pdo;
    try {
        // Find jobs with expired deadlines and no applications
        $stmt = $pdo->prepare("
            SELECT jp.id 
            FROM job_postings jp
            LEFT JOIN (
                SELECT job_id, COUNT(*) as application_count 
                FROM job_applications 
                GROUP BY job_id
            ) ja ON jp.id = ja.job_id
            WHERE jp.deadline IS NOT NULL 
            AND jp.deadline < CURDATE()
            AND (ja.application_count IS NULL OR ja.application_count = 0)
            AND jp.status != 'expired'
        ");
        $stmt->execute();
        $expiredJobs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($expiredJobs)) {
            // Delete expired jobs with no applications
            $placeholders = str_repeat('?,', count($expiredJobs) - 1) . '?';
            $deleteStmt = $pdo->prepare("DELETE FROM job_postings WHERE id IN ($placeholders)");
            $deleteStmt->execute($expiredJobs);
            
            return count($expiredJobs);
        }
        
        return 0;
    } catch (Exception $e) {
        error_log("Error cleaning up expired jobs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if a job has expired based on its deadline
 */
function isJobExpired($deadline) {
    if (empty($deadline)) {
        return false;
    }
    $deadlineDate = new DateTime($deadline);
    $currentDate = new DateTime();
    return $currentDate > $deadlineDate;
}
?>
