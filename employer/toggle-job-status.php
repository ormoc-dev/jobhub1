<?php
include '../config.php';
requireRole('employer');

// Check if request is POST and has required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['job_id']) || !isset($_POST['new_status'])) {
    $_SESSION['error'] = 'Invalid request.';
    redirect('jobs.php');
}

$job_id = (int)$_POST['job_id'];
$new_status = sanitizeInput($_POST['new_status']);

// Validate status
$allowed_statuses = ['active', 'expired', 'pending'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['error'] = 'Invalid status.';
    redirect('jobs.php');
}

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// Verify job ownership
$stmt = $pdo->prepare("SELECT * FROM job_postings WHERE id = ? AND company_id = ?");
$stmt->execute([$job_id, $company['id']]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['error'] = 'Job not found or you do not have permission to modify it.';
    redirect('jobs.php');
}

try {
    // Update job status
    $stmt = $pdo->prepare("UPDATE job_postings SET status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
    $success = $stmt->execute([$new_status, $job_id, $company['id']]);
    
    if ($success) {
        $action_text = '';
        switch($new_status) {
            case 'active':
                $action_text = 'reactivated';
                break;
            case 'expired':
                $action_text = 'marked as expired';
                break;
            case 'pending':
                $action_text = 'marked as pending';
                break;
        }
        $_SESSION['message'] = "Job '{$job['title']}' has been {$action_text} successfully!";
    } else {
        $_SESSION['error'] = 'Failed to update job status. Please try again.';
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error occurred. Please try again.';
}

// Redirect back to jobs list
redirect('jobs.php');
?>
