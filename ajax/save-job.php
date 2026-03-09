<?php
include '../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$job_id = $_POST['job_id'] ?? 0;

if (empty($action) || empty($job_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.* FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    echo json_encode(['success' => false, 'message' => 'Employee profile not found']);
    exit;
}

$employee_id = $profile['id'];

// Check if job exists and is active
$stmt = $pdo->prepare("SELECT id FROM job_postings WHERE id = ? AND status = 'active'");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    echo json_encode(['success' => false, 'message' => 'Job not found or inactive']);
    exit;
}

if ($action === 'save') {
    // Check if already saved
    $stmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
    $stmt->execute([$employee_id, $job_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Job already saved']);
        exit;
    }
    
    // Save job
    $stmt = $pdo->prepare("INSERT INTO saved_jobs (employee_id, job_id, saved_date) VALUES (?, ?, NOW())");
    $stmt->execute([$employee_id, $job_id]);
    
    echo json_encode(['success' => true, 'message' => 'Job saved successfully']);
} elseif ($action === 'unsave') {
    // Remove saved job
    $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
    $stmt->execute([$employee_id, $job_id]);
    
    echo json_encode(['success' => true, 'message' => 'Job removed from saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

