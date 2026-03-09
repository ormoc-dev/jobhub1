<?php
include '../config.php';
requireRole('employer');

// Get job_id parameter
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Redirect to the applications page with the job_id parameter
if ($job_id > 0) {
    redirect("applications.php?job_id=" . $job_id);
} else {
    redirect("applications.php");
}
?>
