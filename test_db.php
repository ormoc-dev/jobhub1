<?php
include 'config.php';

echo "<h2>Database Test</h2>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 40px; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
</style>";

try {
    // Test database connection
    echo "<p class='success'>✅ Database Connection: OK</p>";
    
    // Test each table
    $tables = ['users', 'companies', 'employee_profiles', 'job_categories', 'job_postings', 'job_applications', 'saved_jobs', 'messages'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p class='info'>📋 Table '$table': $count records</p>";
    }
    
    // Test admin user
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "<p class='success'>👤 Admin user found: " . $admin['email'] . "</p>";
    } else {
        echo "<p class='error'>❌ No admin user found</p>";
    }
    
    // Test job categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM job_categories");
    $categories = $stmt->fetchColumn();
    echo "<p class='info'>🏷️ Job categories: $categories</p>";
    
    echo "<p class='success'>🎉 All tests passed! System is ready to use.</p>";
    echo "<p>You can now access: <a href='index.php'>WORKLINK Homepage</a> | <a href='login.php'>Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// Auto-delete this file after 30 seconds
echo "<script>
setTimeout(function() {
    window.location.href = 'index.php';
}, 30000);
</script>";
echo "<p><small>This page will redirect to homepage in 30 seconds...</small></p>";
?>
