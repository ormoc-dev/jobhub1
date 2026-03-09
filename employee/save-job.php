<?php
include '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is an employee
if (!isLoggedIn() || getUserRole() !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get employee profile
$stmt = $pdo->prepare("SELECT id FROM employee_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    echo json_encode(['success' => false, 'message' => 'Profile not found']);
    exit;
}

$employee_id = $profile['id'];
$job_id = (int) ($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

// Check if job exists and is active
$stmt = $pdo->prepare("SELECT id FROM job_postings WHERE id = ? AND status = 'active'");
$stmt->execute([$job_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Job not found']);
    exit;
}

// Check if already saved
$stmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
$stmt->execute([$employee_id, $job_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Unsave the job
    $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
    $stmt->execute([$employee_id, $job_id]);
    echo json_encode(['success' => true, 'saved' => false, 'message' => 'Job removed from saved']);
} else {
    // Save the job
    $stmt = $pdo->prepare("INSERT INTO saved_jobs (employee_id, job_id, saved_date) VALUES (?, ?, NOW())");
    $stmt->execute([$employee_id, $job_id]);
    echo json_encode(['success' => true, 'saved' => true, 'message' => 'Job saved successfully']);
}
