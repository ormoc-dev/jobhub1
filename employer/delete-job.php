<?php
include '../config.php';
requireRole('employer');

// Check if request is POST and has required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['job_id'])) {
    $_SESSION['error'] = 'Invalid request.';
    redirect('jobs.php');
}

$job_id = (int)$_POST['job_id'];

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// Verify job ownership and get job details
$stmt = $pdo->prepare("SELECT * FROM job_postings WHERE id = ? AND company_id = ?");
$stmt->execute([$job_id, $company['id']]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['error'] = 'Job not found or you do not have permission to delete it.';
    redirect('jobs.php');
}

// Check if job has applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE job_id = ?");
$stmt->execute([$job_id]);
$application_count = $stmt->fetchColumn();

try {
    $pdo->beginTransaction();
    
    // If job has applications, we might want to keep the job but mark it as deleted
    // Or delete all related data. For this implementation, we'll delete everything.
    
    if ($application_count > 0) {
        // Delete all applications for this job
        $stmt = $pdo->prepare("DELETE FROM job_applications WHERE job_id = ?");
        $stmt->execute([$job_id]);
        
        // Delete from saved jobs
        $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE job_id = ?");
        $stmt->execute([$job_id]);
    }
    
    // Delete the job posting
    $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ? AND company_id = ?");
    $success = $stmt->execute([$job_id, $company['id']]);
    
    if ($success) {
        $pdo->commit();
        $_SESSION['message'] = "Job '{$job['title']}' and all related data have been deleted successfully!";
        
        // If there were applications, mention that too
        if ($application_count > 0) {
            $_SESSION['message'] .= " ({$application_count} applications were also removed)";
        }
    } else {
        $pdo->rollback();
        $_SESSION['error'] = 'Failed to delete job. Please try again.';
    }
    
} catch(PDOException $e) {
    $pdo->rollback();
    $_SESSION['error'] = 'Database error occurred. Please try again.';
}

// Redirect back to jobs list
redirect('jobs.php');
?>
