<?php
// Legacy alias: redirect to the current view page
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header('Location: view-application.php?id=' . $id, true, 302);
exit;


