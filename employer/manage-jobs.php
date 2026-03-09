<?php
// Redirect to the main job management page
include '../config.php';
requireRole('employer');

// Redirect to jobs.php which is the main job management page
redirect('jobs.php');
?>
