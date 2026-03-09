# Database Setup Script for WORKLINK Job Portal
Write-Host "Setting up WORKLINK Job Portal Database..." -ForegroundColor Green

# MySQL path
$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"

# Check if MySQL is available
if (Test-Path $mysqlPath) {
    Write-Host "MySQL found at: $mysqlPath" -ForegroundColor Green
    
    # Create database and import schema
    Write-Host "Creating database and importing schema..." -ForegroundColor Yellow
    Get-Content "database.sql" | & $mysqlPath -u root
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Database setup completed successfully!" -ForegroundColor Green
        Write-Host ""
        Write-Host "Admin Login Credentials:" -ForegroundColor Cyan
        Write-Host "Email: admin@gmail.com" -ForegroundColor White
        Write-Host "Password: admin123" -ForegroundColor White
        Write-Host ""
        Write-Host "You can now access the system at: http://localhost/jobhub1/" -ForegroundColor Cyan
    } else {
        Write-Host "Database setup failed!" -ForegroundColor Red
    }
} else {
    Write-Host "MySQL not found! Please make sure XAMPP is installed and MySQL is running." -ForegroundColor Red
}

Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
