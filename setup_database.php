<?php
// Database Setup Script for WORKLINK
// This script will create the database and all tables

echo "<h2>WORKLINK Database Setup</h2>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 40px; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
.warning { color: orange; }
</style>";

try {
    // First, connect without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>✅ MySQL Connection: OK</p>";
    
    // Read and execute the database.sql file
    $sqlFile = file_get_contents('database.sql');
    
    if ($sqlFile === false) {
        throw new Exception("Could not read database.sql file");
    }
    
    echo "<p class='info'>📖 Reading database.sql file...</p>";
    
    // Split the SQL file into individual statements
    $statements = explode(';', $sqlFile);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            // Skip errors for statements that might already exist
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "<p class='error'>❌ SQL Error: " . $e->getMessage() . "</p>";
                $errorCount++;
            }
        }
    }
    
    echo "<p class='success'>✅ Executed $successCount SQL statements successfully</p>";
    
    if ($errorCount > 0) {
        echo "<p class='warning'>⚠️ $errorCount statements had errors (mostly expected for existing objects)</p>";
    }
    
    // Test the created database
    $pdo = new PDO("mysql:host=localhost;dbname=jobhub1", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>✅ Connected to jobhub1 database</p>";
    
    // Test each table
    $tables = ['users', 'companies', 'employee_profiles', 'job_categories', 'job_postings', 'job_applications', 'saved_jobs', 'messages', 'login_attempts', 'system_config'];
    
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
        echo "<p class='info'>🔑 Admin login: admin / admin123</p>";
    } else {
        echo "<p class='error'>❌ No admin user found</p>";
    }
    
    // Test job categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM job_categories");
    $categories = $stmt->fetchColumn();
    echo "<p class='info'>🏷️ Job categories: $categories</p>";
    
    echo "<p class='success'>🎉 Database setup completed successfully!</p>";
    echo "<p class='info'>You can now access:</p>";
    echo "<ul>";
    echo "<li><a href='index.php'>🏠 WORKLINK Homepage</a></li>";
    echo "<li><a href='login.php'>🔐 Login Page</a></li>";
    echo "<li><a href='register.php'>📝 Registration Page</a></li>";
    echo "<li><a href='admin/dashboard.php'>⚙️ Admin Dashboard</a></li>";
    echo "</ul>";
    
    echo "<p class='warning'>⚠️ For security, please delete this setup_database.php file after successful setup.</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Setup Error: " . $e->getMessage() . "</p>";
    echo "<p class='info'>Please check:</p>";
    echo "<ul>";
    echo "<li>XAMPP is running</li>";
    echo "<li>MySQL service is started</li>";
    echo "<li>database.sql file exists</li>";
    echo "<li>MySQL root user has no password (or update config.php)</li>";
    echo "</ul>";
}

// Auto-redirect after 60 seconds
echo "<script>
setTimeout(function() {
    window.location.href = 'index.php';
}, 60000);
</script>";
echo "<p><small>This page will redirect to homepage in 60 seconds...</small></p>";
?>
