<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add_user') {
        $role = sanitizeInput($_POST['role']);
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $status = sanitizeInput($_POST['status']);
        
        try {
            $pdo->beginTransaction();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                throw new Exception('Username or email already exists.');
            }
            
            // Insert user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $role, $status]);
            $userId = $pdo->lastInsertId();
            
            if ($role === 'employee') {
                $firstName = sanitizeInput($_POST['first_name']);
                $lastName = sanitizeInput($_POST['last_name']);
                $middleName = sanitizeInput($_POST['middle_name']);
                $sex = sanitizeInput($_POST['sex']);
                $dateOfBirth = sanitizeInput($_POST['date_of_birth']);
                $contactNo = sanitizeInput($_POST['contact_no']);
                $civilStatus = sanitizeInput($_POST['civil_status']);
                $highestEducation = sanitizeInput($_POST['highest_education']);
                
                $employeeId = 'EMP' . str_pad($userId, 6, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("INSERT INTO employee_profiles (user_id, employee_id, first_name, last_name, middle_name, sex, date_of_birth, contact_no, civil_status, highest_education) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $employeeId, $firstName, $lastName, $middleName, $sex, $dateOfBirth, $contactNo, $civilStatus, $highestEducation]);
                
            } elseif ($role === 'employer') {
                $companyName = sanitizeInput($_POST['company_name']);
                $contactNumber = sanitizeInput($_POST['contact_number']);
                $contactEmail = $email;
                $locationAddress = sanitizeInput($_POST['location_address']);
                $description = sanitizeInput($_POST['description']);
                
                $stmt = $pdo->prepare("INSERT INTO companies (user_id, company_name, contact_number, contact_email, location_address, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $companyName, $contactNumber, $contactEmail, $locationAddress, $description, $status]);
            }
            
            $pdo->commit();
            $message = ucfirst($role) . ' user created successfully!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
        
    } else {
        $userId = (int)$_POST['user_id'];
        
        if ($action === 'approve') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'employer') {
                $stmt = $pdo->prepare("UPDATE users u 
                                     JOIN companies c ON u.id = c.user_id 
                                     SET u.status = 'active', u.profile_picture = c.company_logo, c.status = 'active'
                                     WHERE u.id = ?");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$userId]);
            }
            $message = 'User approved successfully! They can now sign in.';
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'employer') {
                $stmt = $pdo->prepare("UPDATE users u 
                                     JOIN companies c ON u.id = c.user_id 
                                     SET u.status = 'suspended', c.status = 'rejected'
                                     WHERE u.id = ?");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$userId]);
            }
            $message = 'User rejected successfully!';
        } elseif ($action === 'suspend') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'employer') {
                $stmt = $pdo->prepare("UPDATE users u 
                                     JOIN companies c ON u.id = c.user_id 
                                     SET u.status = 'suspended', c.status = 'suspended'
                                     WHERE u.id = ?");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$userId]);
            }
            $message = 'User suspended successfully!';
        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'employer') {
                $stmt = $pdo->prepare("UPDATE users u 
                                     JOIN companies c ON u.id = c.user_id 
                                     SET u.status = 'active', c.status = 'active'
                                     WHERE u.id = ?");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$userId]);
            }
            $message = 'User activated successfully!';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $message = 'User deleted successfully!';
        }
    }
}

// Current tab
$currentTab = $_GET['tab'] ?? 'jobseekers';
$search = $_GET['search'] ?? '';

// Build search clause
$searchClause = "";
$searchParams = [];
if (!empty($search)) {
    $searchClause = " AND (u.username LIKE ? OR u.email LIKE ? OR ep.first_name LIKE ? OR ep.last_name LIKE ? OR c.company_name LIKE ?)";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Get data based on current tab
$users = [];
$tabTitle = '';
$tabIcon = '';

switch ($currentTab) {
    case 'jobseekers':
        $sql = "SELECT u.*, ep.first_name, ep.last_name, ep.employee_id, ep.contact_no, ep.sex, ep.civil_status
                FROM users u 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                LEFT JOIN companies c ON u.id = c.user_id 
                WHERE u.role = 'employee' $searchClause
                ORDER BY u.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($searchParams);
        $users = $stmt->fetchAll();
        $tabTitle = 'Jobseekers';
        $tabIcon = 'fa-user-tie';
        break;
        
    case 'employers':
        $sql = "SELECT u.*, c.company_name, c.contact_number, c.location_address, c.status as company_status
                FROM users u 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                LEFT JOIN companies c ON u.id = c.user_id 
                WHERE u.role = 'employer' $searchClause
                ORDER BY u.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($searchParams);
        $users = $stmt->fetchAll();
        $tabTitle = 'Employers';
        $tabIcon = 'fa-building';
        break;
        
    case 'admins':
        $sql = "SELECT u.*, ep.first_name, ep.last_name, c.company_name
                FROM users u 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                LEFT JOIN companies c ON u.id = c.user_id 
                WHERE u.role = 'admin' $searchClause
                ORDER BY u.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($searchParams);
        $users = $stmt->fetchAll();
        $tabTitle = 'Admin Accounts';
        $tabIcon = 'fa-user-shield';
        break;
        
    case 'status':
        $statusFilter = $_GET['status_filter'] ?? 'all';
        $statusClause = "";
        if ($statusFilter !== 'all') {
            $statusClause = " AND u.status = '$statusFilter'";
        }
        $sql = "SELECT u.*, ep.first_name, ep.last_name, ep.employee_id, c.company_name
                FROM users u 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                LEFT JOIN companies c ON u.id = c.user_id 
                WHERE 1=1 $statusClause $searchClause
                ORDER BY u.status ASC, u.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($searchParams);
        $users = $stmt->fetchAll();
        $tabTitle = 'Account Status';
        $tabIcon = 'fa-toggle-on';
        break;
}

// Get statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['jobseekers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$stats['employers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employer'")->fetchColumn();
$stats['admins'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$stats['pending_employers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'pending'")->fetchColumn();
$stats['active'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$stats['suspended'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --coral: #ff6b6b;
            --coral-light: #ff8787;
            --coral-dark: #ee5a5a;
            --ocean: #4ecdc4;
            --ocean-dark: #3db9b1;
            --golden: #ffe66d;
            --golden-dark: #ffd93d;
            --navy: #2c3e50;
            --navy-light: #34495e;
            --cream: #fafafa;
            --smoke: #f5f6fa;
            --charcoal: #2d3436;
            --silver: #dfe6e9;
        }
        
        .users-page {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
        }
        
        .users-page .admin-main-content {
            background: transparent;
            padding: 30px;
        }
        
        /* Header Card */
        .header-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-card h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 2.2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }
        
        .header-card p {
            color: #6c757d;
            margin: 0;
        }
        
        .btn-add-user {
            background: linear-gradient(135deg, var(--coral), var(--coral-dark));
            border: none;
            color: white;
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
            transition: all 0.3s ease;
        }
        
        .btn-add-user:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255, 107, 107, 0.5);
            color: white;
        }
        
        /* Alert Styles */
        .alert-success-custom {
            background: linear-gradient(135deg, #00b894, #00cec9);
            border: none;
            color: white;
            border-radius: 16px;
            padding: 18px 25px;
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.3);
        }
        
        .alert-danger-custom {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
            border: none;
            color: white;
            border-radius: 16px;
            padding: 18px 25px;
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
        }
        
        .alert-warning-custom {
            background: linear-gradient(135deg, #fdcb6e, #f39c12);
            border: none;
            color: var(--charcoal);
            border-radius: 16px;
            padding: 20px 25px;
            box-shadow: 0 8px 25px rgba(253, 203, 110, 0.4);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 20px 20px 0 0;
        }
        
        .stat-card.total::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-card.jobseekers::before { background: linear-gradient(90deg, #4ecdc4, #44a08d); }
        .stat-card.employers::before { background: linear-gradient(90deg, #f093fb, #f5576c); }
        .stat-card.pending::before { background: linear-gradient(90deg, #fdcb6e, #f39c12); }
        .stat-card.active::before { background: linear-gradient(90deg, #00b894, #00cec9); }
        .stat-card.suspended::before { background: linear-gradient(90deg, #e17055, #d63031); }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.pending.has-pending {
            animation: gentle-pulse 2s infinite;
        }
        
        @keyframes gentle-pulse {
            0%, 100% { box-shadow: 0 10px 40px rgba(253, 203, 110, 0.3); }
            50% { box-shadow: 0 10px 50px rgba(253, 203, 110, 0.6); }
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 22px;
        }
        
        .stat-card.total .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-card.jobseekers .stat-icon { background: linear-gradient(135deg, #4ecdc4, #44a08d); color: white; }
        .stat-card.employers .stat-icon { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
        .stat-card.pending .stat-icon { background: linear-gradient(135deg, #fdcb6e, #f39c12); color: white; }
        .stat-card.active .stat-icon { background: linear-gradient(135deg, #00b894, #00cec9); color: white; }
        .stat-card.suspended .stat-icon { background: linear-gradient(135deg, #e17055, #d63031); color: white; }
        
        .stat-number {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--charcoal);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 500;
        }
        
        /* Navigation Pills */
        .nav-pills-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 8px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .nav-pills-custom .nav-link {
            border-radius: 14px;
            padding: 14px 22px;
            color: #6c757d;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            background: transparent;
            position: relative;
        }
        
        .nav-pills-custom .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        
        .nav-pills-custom .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .nav-pills-custom .nav-link .badge {
            font-size: 11px;
            padding: 4px 8px;
            margin-left: 8px;
            border-radius: 8px;
        }
        
        .nav-pills-custom .nav-link.active .badge {
            background: rgba(255, 255, 255, 0.3) !important;
        }
        
        /* Main Content Card */
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }
        
        .content-card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .content-card-header h5 {
            color: white;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 1.3rem;
            margin: 0;
        }
        
        .content-card-header .badge {
            background: rgba(255, 255, 255, 0.2);
            font-size: 14px;
            padding: 6px 12px;
            margin-left: 10px;
        }
        
        .search-box {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            padding: 10px 16px;
            width: 220px;
            transition: all 0.3s ease;
        }
        
        .search-box::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .search-box:focus {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .btn-search {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 12px;
            padding: 10px 16px;
        }
        
        .btn-search:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .filter-select {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            padding: 10px 16px;
        }
        
        .filter-select option {
            background: #667eea;
            color: white;
        }
        
        /* Table Styles */
        .users-table {
            margin: 0;
        }
        
        .users-table thead th {
            background: rgba(102, 126, 234, 0.08);
            color: #667eea;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 18px 20px;
            border: none;
        }
        
        .users-table tbody td {
            padding: 18px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
            color: var(--charcoal);
        }
        
        .users-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .users-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.04);
        }
        
        .users-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* User Avatar */
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
        }
        
        .user-avatar.jobseeker { background: linear-gradient(135deg, #4ecdc4, #44a08d); }
        .user-avatar.employer { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .user-avatar.admin { background: linear-gradient(135deg, #667eea, #764ba2); }
        
        .user-info strong {
            font-weight: 600;
            color: var(--charcoal);
        }
        
        .user-info small {
            color: #6c757d;
        }
        
        /* Badges */
        .badge-role {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-role.employee { background: linear-gradient(135deg, #4ecdc4, #44a08d); color: white; }
        .badge-role.employer { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
        .badge-role.admin { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        
        .badge-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-status.active { background: rgba(0, 184, 148, 0.15); color: #00b894; }
        .badge-status.pending { background: rgba(253, 203, 110, 0.2); color: #f39c12; }
        .badge-status.suspended { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; }
        
        /* Action Buttons */
        .action-btn {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            margin: 2px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
        }
        
        .btn-approve:hover {
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.4);
            color: white;
        }
        
        .btn-reject, .btn-delete {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .btn-reject:hover, .btn-delete:hover {
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
            color: white;
        }
        
        .btn-suspend {
            background: linear-gradient(135deg, #fdcb6e, #f39c12);
            color: white;
            box-shadow: 0 4px 15px rgba(253, 203, 110, 0.3);
        }
        
        .btn-suspend:hover {
            box-shadow: 0 6px 20px rgba(253, 203, 110, 0.4);
            color: white;
        }
        
        .btn-activate {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-activate:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-state i {
            font-size: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            opacity: 0.5;
        }
        
        .empty-state h5 {
            color: var(--charcoal);
            font-weight: 600;
        }
        
        .empty-state p {
            color: #6c757d;
        }
        
        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 24px 24px 0 0;
            padding: 25px 30px;
            border: none;
        }
        
        .modal-title {
            color: white;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #f0f0f0;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        
        .role-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .role-option:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .role-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .btn-cancel {
            background: #f0f0f0;
            color: #6c757d;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .btn-cancel:hover {
            background: #e0e0e0;
            color: #6c757d;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 4px;
        }
    </style>
</head>
<body class="admin-layout users-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Header -->
        <div class="header-card d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-users-cog me-3"></i>User Management</h1>
                <p>Manage users, verify employers, and control account access</p>
            </div>
            <button type="button" class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success-custom alert-dismissible fade show mb-4">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger-custom alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($stats['pending_employers'] > 0): ?>
            <div class="alert alert-warning-custom d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bell fa-lg me-3"></i>
                    <div>
                        <strong><?php echo $stats['pending_employers']; ?> Employer<?php echo $stats['pending_employers'] > 1 ? 's' : ''; ?> Awaiting Approval</strong>
                        <p class="mb-0 small">Go to Company Management > Employer Verification to approve</p>
                    </div>
                </div>
                <a href="company.php?tab=verification" class="btn btn-sm" style="background: white; color: #f39c12; font-weight: 600; border-radius: 10px; padding: 8px 16px;">
                    Review Now <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card jobseekers">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo $stats['jobseekers']; ?></div>
                <div class="stat-label">Jobseekers</div>
            </div>
            <div class="stat-card employers">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo $stats['employers']; ?></div>
                <div class="stat-label">Employers</div>
            </div>
            <div class="stat-card pending <?php echo $stats['pending'] > 0 ? 'has-pending' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card active">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card suspended">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-number"><?php echo $stats['suspended']; ?></div>
                <div class="stat-label">Suspended</div>
            </div>
        </div>

        <!-- Navigation Pills -->
        <nav class="nav-pills-custom">
            <a class="nav-link <?php echo $currentTab === 'jobseekers' ? 'active' : ''; ?>" href="?tab=jobseekers">
                <i class="fas fa-user-tie me-2"></i>Jobseekers
                <span class="badge bg-info"><?php echo $stats['jobseekers']; ?></span>
            </a>
            <a class="nav-link <?php echo $currentTab === 'employers' ? 'active' : ''; ?>" href="?tab=employers">
                <i class="fas fa-building me-2"></i>Employers
                <span class="badge bg-success"><?php echo $stats['employers']; ?></span>
            </a>
            <a class="nav-link <?php echo $currentTab === 'admins' ? 'active' : ''; ?>" href="?tab=admins">
                <i class="fas fa-user-shield me-2"></i>Admin Accounts
                <span class="badge bg-danger"><?php echo $stats['admins']; ?></span>
            </a>
            <a class="nav-link <?php echo $currentTab === 'status' ? 'active' : ''; ?>" href="?tab=status">
                <i class="fas fa-toggle-on me-2"></i>Account Status
            </a>
        </nav>

        <!-- Content Card -->
        <div class="content-card">
            <div class="content-card-header">
                <h5>
                    <i class="fas <?php echo $tabIcon; ?> me-2"></i><?php echo $tabTitle; ?>
                    <span class="badge"><?php echo count($users); ?></span>
                </h5>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($currentTab === 'status'): ?>
                        <select class="filter-select" onchange="window.location.href='?tab=status&status_filter='+this.value">
                            <option value="all" <?php echo ($_GET['status_filter'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo ($_GET['status_filter'] ?? '') === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="suspended" <?php echo ($_GET['status_filter'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended Only</option>
                            <option value="pending" <?php echo ($_GET['status_filter'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                        </select>
                    <?php endif; ?>
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
                        <input type="text" name="search" class="search-box" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-search">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>No users found</h5>
                    <p>No users match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <?php if ($currentTab === 'status'): ?>
                                    <th>Role</th>
                                <?php endif; ?>
                                <th>Email</th>
                                <?php if ($currentTab === 'employers'): ?>
                                    <th>Company</th>
                                    <th>Contact</th>
                                <?php elseif ($currentTab === 'jobseekers'): ?>
                                    <th>Contact</th>
                                    <th>Gender</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar <?php echo $user['role']; ?> me-3">
                                                <?php 
                                                if ($user['role'] === 'employee' && !empty($user['first_name'])) {
                                                    echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                                                } elseif ($user['role'] === 'employer' && !empty($user['company_name'])) {
                                                    echo strtoupper(substr($user['company_name'], 0, 2));
                                                } else {
                                                    echo strtoupper(substr($user['username'], 0, 2));
                                                }
                                                ?>
                                            </div>
                                            <div class="user-info">
                                                <strong>
                                                    <?php if ($user['role'] === 'employee'): ?>
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    <?php elseif ($user['role'] === 'employer'): ?>
                                                        <?php echo htmlspecialchars($user['company_name'] ?? $user['username']); ?>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    <?php endif; ?>
                                                </strong>
                                                <br><small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                <?php if ($user['role'] === 'employee' && !empty($user['employee_id'])): ?>
                                                    <br><small>ID: <?php echo htmlspecialchars($user['employee_id']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <?php if ($currentTab === 'status'): ?>
                                        <td>
                                            <span class="badge-role <?php echo $user['role']; ?>">
                                                <i class="fas <?php echo $user['role'] === 'employee' ? 'fa-user' : ($user['role'] === 'employer' ? 'fa-building' : 'fa-shield-alt'); ?> me-1"></i>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <?php if ($currentTab === 'employers'): ?>
                                        <td><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['contact_number'] ?? '-'); ?></td>
                                    <?php elseif ($currentTab === 'jobseekers'): ?>
                                        <td><?php echo htmlspecialchars($user['contact_no'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['sex'] ?? '-'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge-status <?php echo $user['status']; ?>">
                                            <i class="fas <?php echo $user['status'] === 'active' ? 'fa-check-circle' : ($user['status'] === 'pending' ? 'fa-clock' : 'fa-ban'); ?> me-1"></i>
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <div class="d-flex flex-wrap">
                                                <?php if ($user['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-approve" onclick="return confirm('Approve this user?')" title="Approve">
                                                            <i class="fas fa-check me-1"></i>Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-reject" onclick="return confirm('Reject this user?')" title="Reject">
                                                            <i class="fas fa-times me-1"></i>Reject
                                                        </button>
                                                    </form>
                                                <?php elseif ($user['status'] === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="suspend">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-suspend" onclick="return confirm('Suspend this user?')" title="Suspend">
                                                            <i class="fas fa-ban me-1"></i>Suspend
                                                        </button>
                                                    </form>
                                                <?php elseif ($user['status'] === 'suspended'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-activate" onclick="return confirm('Activate this user?')" title="Activate">
                                                            <i class="fas fa-check me-1"></i>Activate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn btn-delete" onclick="return confirm('Delete this user permanently?')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-role admin"><i class="fas fa-shield-alt me-1"></i>Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addUserForm">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <!-- Role Selection -->
                        <div class="mb-4">
                            <label class="form-label">User Role *</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="role-option" onclick="selectRole('employee')">
                                        <input class="form-check-input d-none" type="radio" name="role" id="roleEmployee" value="employee" onchange="toggleUserFields()">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar jobseeker me-3" style="width:45px;height:45px;font-size:16px;">
                                                <i class="fas fa-user-tie"></i>
                                            </div>
                                            <div>
                                                <strong>Jobseeker</strong>
                                                <br><small class="text-muted">Can browse and apply for jobs</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="role-option" onclick="selectRole('employer')">
                                        <input class="form-check-input d-none" type="radio" name="role" id="roleEmployer" value="employer" onchange="toggleUserFields()">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar employer me-3" style="width:45px;height:45px;font-size:16px;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <div>
                                                <strong>Employer</strong>
                                                <br><small class="text-muted">Can post jobs (requires approval)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Common Account Fields -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="active">Active (Can sign in)</option>
                                    <option value="pending">Pending (Needs approval)</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>

                        <!-- Employee Fields -->
                        <div id="employeeFields" style="display: none;">
                            <hr class="my-4">
                            <h6 class="mb-3" style="color: #4ecdc4;"><i class="fas fa-user-tie me-2"></i>Jobseeker Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" name="middle_name">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Gender *</label>
                                    <select class="form-select" name="sex">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" name="date_of_birth">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_no">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Civil Status *</label>
                                    <select class="form-select" name="civil_status">
                                        <option value="">Select Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Highest Education *</label>
                                <input type="text" class="form-control" name="highest_education" placeholder="e.g., Bachelor's Degree in Computer Science">
                            </div>
                        </div>

                        <!-- Employer Fields -->
                        <div id="employerFields" style="display: none;">
                            <hr class="my-4">
                            <div class="alert" style="background: rgba(253, 203, 110, 0.2); border: 2px solid #fdcb6e; border-radius: 12px; color: #6c5a1d;">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> If status is "Pending", employer will need admin approval to sign in.
                            </div>
                            <h6 class="mb-3" style="color: #f093fb;"><i class="fas fa-building me-2"></i>Company Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" name="company_name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_number">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Address *</label>
                                <textarea class="form-control" name="location_address" rows="2" placeholder="Complete company address"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Description</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the company"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-save">
                            <i class="fas fa-save me-1"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectRole(role) {
            document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
            if (role === 'employee') {
                document.getElementById('roleEmployee').checked = true;
                document.getElementById('roleEmployee').closest('.role-option').classList.add('selected');
            } else {
                document.getElementById('roleEmployer').checked = true;
                document.getElementById('roleEmployer').closest('.role-option').classList.add('selected');
            }
            toggleUserFields();
        }
        
        function toggleUserFields() {
            const roleEmployee = document.getElementById('roleEmployee').checked;
            const roleEmployer = document.getElementById('roleEmployer').checked;
            
            const employeeFields = document.getElementById('employeeFields');
            const employerFields = document.getElementById('employerFields');
            
            employeeFields.style.display = 'none';
            employerFields.style.display = 'none';
            
            employeeFields.querySelectorAll('input, select, textarea').forEach(field => {
                field.required = false;
            });
            employerFields.querySelectorAll('input, select, textarea').forEach(field => {
                field.required = false;
            });
            
            if (roleEmployee) {
                employeeFields.style.display = 'block';
                employeeFields.querySelectorAll('input[name="first_name"], input[name="last_name"], select[name="sex"], input[name="date_of_birth"], input[name="contact_no"], select[name="civil_status"], input[name="highest_education"]').forEach(field => {
                    field.required = true;
                });
            } else if (roleEmployer) {
                employerFields.style.display = 'block';
                employerFields.querySelectorAll('input[name="company_name"], input[name="contact_number"], textarea[name="location_address"]').forEach(field => {
                    field.required = true;
                });
            }
        }
        
        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('addUserForm').reset();
            document.getElementById('employeeFields').style.display = 'none';
            document.getElementById('employerFields').style.display = 'none';
            document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
        });
    </script>
</body>
</html>
