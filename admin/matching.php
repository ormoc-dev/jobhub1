<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Note: Tables (matching_rules, skill_taxonomy, recommendation_settings) 
// are defined in sql/all_additional_tables.sql

// Insert default recommendation settings if they don't exist
$defaultSettings = [
    ['min_match_score', '60', 'number', 'Minimum match score percentage to show recommendations'],
    ['max_recommendations', '10', 'number', 'Maximum number of job recommendations per user'],
    ['skill_weight', '40', 'number', 'Weight percentage for skill matching'],
    ['experience_weight', '25', 'number', 'Weight percentage for experience matching'],
    ['education_weight', '20', 'number', 'Weight percentage for education matching'],
    ['location_weight', '15', 'number', 'Weight percentage for location matching'],
    ['enable_ai_matching', '1', 'boolean', 'Enable AI-powered job matching'],
    ['enable_email_notifications', '1', 'boolean', 'Send email notifications for new matches'],
    ['refresh_interval', '24', 'number', 'Hours between recommendation refreshes'],
    ['consider_salary_range', '1', 'boolean', 'Include salary expectations in matching algorithm'],
    ['boost_premium_jobs', '1', 'boolean', 'Give higher visibility to premium job postings'],
    ['match_algorithm', 'weighted', 'string', 'Matching algorithm type (weighted, ai, hybrid)']
];

foreach ($defaultSettings as $setting) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO recommendation_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
    $stmt->execute($setting);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Matching Rules Actions
    if ($action === 'add_rule') {
        $ruleName = sanitizeInput($_POST['rule_name']);
        $ruleType = sanitizeInput($_POST['rule_type']);
        $weight = floatval($_POST['weight']);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $description = sanitizeInput($_POST['description'] ?? '');
        
        if (empty($ruleName)) {
            $error = 'Rule name is required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO matching_rules (rule_name, rule_type, weight, is_required, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ruleName, $ruleType, $weight, $isRequired, $description]);
            $message = 'Matching rule added successfully!';
        }
    }
    
    elseif ($action === 'edit_rule') {
        $ruleId = (int)$_POST['rule_id'];
        $ruleName = sanitizeInput($_POST['rule_name']);
        $ruleType = sanitizeInput($_POST['rule_type']);
        $weight = floatval($_POST['weight']);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $description = sanitizeInput($_POST['description'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE matching_rules SET rule_name = ?, rule_type = ?, weight = ?, is_required = ?, description = ? WHERE id = ?");
        $stmt->execute([$ruleName, $ruleType, $weight, $isRequired, $description, $ruleId]);
        $message = 'Matching rule updated successfully!';
    }
    
    elseif ($action === 'toggle_rule') {
        $ruleId = (int)$_POST['rule_id'];
        $stmt = $pdo->prepare("UPDATE matching_rules SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$ruleId]);
        $message = 'Rule status updated!';
    }
    
    elseif ($action === 'delete_rule') {
        $ruleId = (int)$_POST['rule_id'];
        $stmt = $pdo->prepare("DELETE FROM matching_rules WHERE id = ?");
        $stmt->execute([$ruleId]);
        $message = 'Matching rule deleted!';
    }
    
    // Skill Taxonomy Actions
    elseif ($action === 'add_skill') {
        $skillName = sanitizeInput($_POST['skill_name']);
        $category = sanitizeInput($_POST['category']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $synonyms = !empty($_POST['synonyms']) ? json_encode(array_map('trim', explode(',', $_POST['synonyms']))) : null;
        $proficiencyLevels = json_encode(['Beginner', 'Intermediate', 'Advanced', 'Expert']);
        
        if (empty($skillName) || empty($category)) {
            $error = 'Skill name and category are required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO skill_taxonomy (skill_name, category, parent_id, synonyms, proficiency_levels) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$skillName, $category, $parentId, $synonyms, $proficiencyLevels]);
            $message = 'Skill added to taxonomy!';
        }
    }
    
    elseif ($action === 'edit_skill') {
        $skillId = (int)$_POST['skill_id'];
        $skillName = sanitizeInput($_POST['skill_name']);
        $category = sanitizeInput($_POST['category']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $synonyms = !empty($_POST['synonyms']) ? json_encode(array_map('trim', explode(',', $_POST['synonyms']))) : null;
        
        $stmt = $pdo->prepare("UPDATE skill_taxonomy SET skill_name = ?, category = ?, parent_id = ?, synonyms = ? WHERE id = ?");
        $stmt->execute([$skillName, $category, $parentId, $synonyms, $skillId]);
        $message = 'Skill updated successfully!';
    }
    
    elseif ($action === 'toggle_skill') {
        $skillId = (int)$_POST['skill_id'];
        $stmt = $pdo->prepare("UPDATE skill_taxonomy SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$skillId]);
        $message = 'Skill status updated!';
    }
    
    elseif ($action === 'delete_skill') {
        $skillId = (int)$_POST['skill_id'];
        $stmt = $pdo->prepare("DELETE FROM skill_taxonomy WHERE id = ?");
        $stmt->execute([$skillId]);
        $message = 'Skill removed from taxonomy!';
    }
    
    // Recommendation Settings Actions
    elseif ($action === 'update_settings') {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $pdo->prepare("UPDATE recommendation_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            
            $pdo->commit();
            $message = 'Recommendation settings updated successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
}

// Fetch all data
$matchingRules = $pdo->query("SELECT * FROM matching_rules ORDER BY rule_type, weight DESC")->fetchAll();
$skills = $pdo->query("SELECT s.*, p.skill_name as parent_name FROM skill_taxonomy s LEFT JOIN skill_taxonomy p ON s.parent_id = p.id ORDER BY s.category, s.skill_name")->fetchAll();
$settings = $pdo->query("SELECT setting_key, setting_value FROM recommendation_settings ORDER BY setting_key")->fetchAll(PDO::FETCH_KEY_PAIR);
$allSettings = $pdo->query("SELECT * FROM recommendation_settings ORDER BY setting_key")->fetchAll();
$parentSkills = $pdo->query("SELECT id, skill_name FROM skill_taxonomy WHERE parent_id IS NULL ORDER BY skill_name")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM skill_taxonomy ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Current tab
$currentTab = $_GET['tab'] ?? 'rules';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Matching & System Logic - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
        <style>
        .matching-admin-page .admin-main-content {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 0 2.5rem;
        }

        .matching-page-header {
            padding: 1.25rem 1.5rem;
            margin: 0 1rem 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .matching-page-header h1 {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .matching-page-header h1 i {
            color: #6366f1;
            opacity: 0.95;
        }

        .matching-page-header p {
            color: #64748b;
            margin: 0.35rem 0 0;
            font-size: 0.95rem;
        }

        .matching-alert {
            border-radius: 12px;
            border: 1px solid transparent;
            margin-bottom: 1rem;
        }

        .matching-alert--success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .matching-alert--danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .matching-tabs {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 1.5rem;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
            border: 1px solid #e2e8f0;
            box-shadow: none;
        }

        .matching-tabs .nav-link {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.875rem;
            color: #64748b;
            border: none;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .matching-tabs .nav-link:hover {
            background: rgba(255, 255, 255, 0.9);
            color: #4338ca;
        }

        .matching-tabs .nav-link.active {
            background: #fff;
            color: #4338ca;
            box-shadow: 0 1px 4px rgba(30, 58, 138, 0.12);
        }

        .matching-tabs .nav-link i {
            font-size: 1rem;
        }

        .matching-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .matching-card:hover {
            transform: none;
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.08);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .matching-card .card-header {
            background: linear-gradient(120deg, #fafbff 0%, #f8fafc 100%);
            border-bottom: 1px solid #e2e8f0;
            padding: 1.1rem 1.35rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .matching-card .card-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.05rem;
            color: #1e3a8a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .matching-card .card-header h5 i {
            color: #6366f1;
        }

        .matching-card .card-body {
            padding: 1.25rem;
        }

        .stat-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            border-top: 3px solid #6366f1;
        }

        .stat-box::before {
            display: none;
        }

        .stat-box .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
            color: #0f172a;
        }

        .stat-box .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 6px;
            font-weight: 500;
            opacity: 1;
        }

        .stat-box.success { border-top-color: #059669; }
        .stat-box.warning { border-top-color: #d97706; }
        .stat-box.info { border-top-color: #0891b2; }

        .matching-table {
            border-radius: 0;
            overflow: visible;
        }

        .matching-table thead th {
            background: #f1f5f9;
            color: #64748b;
            font-weight: 600;
            padding: 12px 14px;
            border: none;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .matching-table tbody td {
            padding: 12px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }

        .matching-table tbody tr:last-child td {
            border-bottom: none;
        }

        .matching-table tbody tr:hover {
            background: #f8fafc;
        }

        .type-badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .type-badge.skill { background: #dbeafe; color: #1e40af; }
        .type-badge.experience { background: #fef3c7; color: #b45309; }
        .type-badge.education { background: #dcfce7; color: #15803d; }
        .type-badge.location { background: #fce7f3; color: #be185d; }
        .type-badge.salary { background: #e0e7ff; color: #4338ca; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #ecfdf5;
            color: #047857;
        }

        .status-badge.inactive {
            background: #fef2f2;
            color: #b91c1c;
        }

        .weight-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .weight-bar {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .weight-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #818cf8);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .weight-value {
            font-weight: 700;
            color: #4338ca;
            min-width: 45px;
            font-size: 0.875rem;
        }

        .btn-matching {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: box-shadow 0.2s, transform 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-matching:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            color: white;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            transition: background 0.2s;
        }

        .btn-action.edit {
            background: #fff;
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.35);
        }
        .btn-action.edit:hover { background: #eef2ff; color: #1d4ed8; }

        .btn-action.toggle {
            background: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }
        .btn-action.toggle:hover { background: #fef3c7; }

        .btn-action.delete {
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .btn-action.delete:hover { background: #fef2f2; }

        .settings-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .settings-group-title {
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-group-title i {
            color: #6366f1;
        }

        .setting-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: box-shadow 0.2s;
        }

        .setting-item:hover {
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        .setting-item:last-child {
            margin-bottom: 0;
        }

        .setting-label {
            font-weight: 600;
            color: #0f172a;
        }

        .setting-desc {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 2px;
        }

        .setting-input {
            width: 120px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 600;
            text-align: center;
            color: #0f172a;
            background: #fff;
        }

        .setting-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
            outline: none;
        }

        .toggle-switch {
            position: relative;
            width: 52px;
            height: 28px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #cbd5e1;
            border-radius: 28px;
            transition: 0.3s;
        }

        .toggle-slider::before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #6366f1, #818cf8);
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(24px);
        }

        .skill-tree {
            padding: 0;
            list-style: none;
        }

        .skill-tree-item {
            background: #fff;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #6366f1;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .skill-tree-item:hover {
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            transform: none;
        }

        .skill-tree-item.child {
            margin-left: 1.5rem;
            border-left-color: #818cf8;
        }

        .category-badge {
            background: #eef2ff;
            color: #4338ca;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid #c7d2fe;
        }

        .matching-admin-page .form-control,
        .matching-admin-page .form-select {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 14px;
            background: #fff;
            color: #0f172a;
        }

        .matching-admin-page .form-control:focus,
        .matching-admin-page .form-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .form-select--narrow {
            width: 140px;
        }

        .matching-admin-page .modal-content {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }

        .matching-admin-page .modal-header {
            background: linear-gradient(120deg, #f5f3ff 0%, #eef2ff 100%);
            color: #1e3a8a;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 20px;
        }

        .matching-admin-page .modal-header .modal-title {
            color: #1e3a8a;
            font-weight: 700;
        }

        .matching-admin-page .modal-header .btn-close {
            filter: none;
        }

        .matching-admin-page .modal-body {
            padding: 1.25rem 1.5rem;
            background: #fff;
        }

        .matching-admin-page .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 14px 1.5rem 1.25rem;
            background: #fafafa;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeInUp 0.45s ease forwards;
        }

        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }
        .animate-delay-4 { animation-delay: 0.4s; }

        @media (max-width: 768px) {
            .matching-tabs {
                width: 100%;
                justify-content: center;
            }

            .matching-tabs .nav-link {
                flex: 1;
                justify-content: center;
                padding: 10px 14px;
            }

            .setting-item {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="admin-layout matching-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="matching-page-header">
            <h1><i class="fas fa-brain me-2"></i>AI Matching & System Logic</h1>
            <p>Configure matching rules, skill taxonomy, and recommendation algorithms</p>
        </div>

        <div class="container-fluid px-4">
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show animate-in matching-alert matching-alert--success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show animate-in matching-alert matching-alert--danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 animate-in animate-delay-1">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($matchingRules); ?></div>
                        <div class="stat-label">Matching Rules</div>
                    </div>
                </div>
                <div class="col-md-3 animate-in animate-delay-2">
                    <div class="stat-box success">
                        <div class="stat-number"><?php echo count($skills); ?></div>
                        <div class="stat-label">Skills in Taxonomy</div>
                    </div>
                </div>
                <div class="col-md-3 animate-in animate-delay-3">
                    <div class="stat-box warning">
                        <div class="stat-number"><?php echo count(array_unique(array_column($skills, 'category'))); ?></div>
                        <div class="stat-label">Skill Categories</div>
                    </div>
                </div>
                <div class="col-md-3 animate-in animate-delay-4">
                    <div class="stat-box info">
                        <div class="stat-number"><?php echo $settings['min_match_score'] ?? 60; ?>%</div>
                        <div class="stat-label">Min Match Score</div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="text-center mb-4">
                <nav class="matching-tabs">
                    <a class="nav-link <?php echo $currentTab === 'rules' ? 'active' : ''; ?>" href="?tab=rules">
                        <i class="fas fa-gavel"></i>
                        <span>Matching Rules</span>
                    </a>
                    <a class="nav-link <?php echo $currentTab === 'taxonomy' ? 'active' : ''; ?>" href="?tab=taxonomy">
                        <i class="fas fa-sitemap"></i>
                        <span>Skill Taxonomy</span>
                    </a>
                    <a class="nav-link <?php echo $currentTab === 'settings' ? 'active' : ''; ?>" href="?tab=settings">
                        <i class="fas fa-sliders-h"></i>
                        <span>Recommendation Settings</span>
                    </a>
                </nav>
            </div>

            <!-- Tab Content -->
            <?php if ($currentTab === 'rules'): ?>
            <!-- Matching Rules Tab -->
            <div class="matching-card animate-in">
                <div class="card-header">
                    <h5><i class="fas fa-gavel"></i> Matching Rules Configuration</h5>
                    <button class="btn btn-matching" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                        <i class="fas fa-plus"></i> Add Rule
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table matching-table mb-0">
                            <thead>
                                <tr>
                                    <th>Rule Name</th>
                                    <th>Type</th>
                                    <th>Weight</th>
                                    <th>Required</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($matchingRules)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No matching rules configured yet</p>
                                            <button class="btn btn-matching btn-sm" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                                                <i class="fas fa-plus"></i> Add Your First Rule
                                            </button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($matchingRules as $rule): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($rule['rule_name']); ?></strong>
                                                <?php if (!empty($rule['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($rule['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="type-badge <?php echo $rule['rule_type']; ?>">
                                                    <?php echo ucfirst($rule['rule_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="weight-indicator">
                                                    <div class="weight-bar">
                                                        <div class="weight-fill" style="width: <?php echo $rule['weight'] * 100; ?>%"></div>
                                                    </div>
                                                    <span class="weight-value"><?php echo number_format($rule['weight'], 2); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($rule['is_required']): ?>
                                                    <span class="badge bg-danger">Required</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Optional</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $rule['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editRuleModal<?php echo $rule['id']; ?>" title="Edit">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_rule">
                                                        <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                        <button type="submit" class="btn-action toggle" title="Toggle Status">
                                                            <i class="fas fa-power-off"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                                                        <input type="hidden" name="action" value="delete_rule">
                                                        <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                        <button type="submit" class="btn-action delete" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Edit Rule Modal -->
                                        <div class="modal fade" id="editRuleModal<?php echo $rule['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Matching Rule</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="edit_rule">
                                                            <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Rule Name *</label>
                                                                <input type="text" class="form-control" name="rule_name" value="<?php echo htmlspecialchars($rule['rule_name']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Rule Type *</label>
                                                                <select class="form-select" name="rule_type" required>
                                                                    <option value="skill" <?php echo $rule['rule_type'] === 'skill' ? 'selected' : ''; ?>>Skill</option>
                                                                    <option value="experience" <?php echo $rule['rule_type'] === 'experience' ? 'selected' : ''; ?>>Experience</option>
                                                                    <option value="education" <?php echo $rule['rule_type'] === 'education' ? 'selected' : ''; ?>>Education</option>
                                                                    <option value="location" <?php echo $rule['rule_type'] === 'location' ? 'selected' : ''; ?>>Location</option>
                                                                    <option value="salary" <?php echo $rule['rule_type'] === 'salary' ? 'selected' : ''; ?>>Salary</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Weight (0.00 - 1.00) *</label>
                                                                <input type="number" class="form-control" name="weight" min="0" max="1" step="0.01" value="<?php echo $rule['weight']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Description</label>
                                                                <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($rule['description'] ?? ''); ?></textarea>
                                                            </div>
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input" name="is_required" id="editRequired<?php echo $rule['id']; ?>" <?php echo $rule['is_required'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="editRequired<?php echo $rule['id']; ?>">This rule is required for matching</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-matching">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php elseif ($currentTab === 'taxonomy'): ?>
            <!-- Skill Taxonomy Tab -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="matching-card animate-in">
                        <div class="card-header">
                            <h5><i class="fas fa-sitemap"></i> Skill Taxonomy</h5>
                            <button class="btn btn-matching" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                <i class="fas fa-plus"></i> Add Skill
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($skills)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-code-branch fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No skills in taxonomy yet</p>
                                    <button class="btn btn-matching btn-sm" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                        <i class="fas fa-plus"></i> Add Your First Skill
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php 
                                $groupedSkills = [];
                                foreach ($skills as $skill) {
                                    $groupedSkills[$skill['category']][] = $skill;
                                }
                                ?>
                                <?php foreach ($groupedSkills as $category => $categorySkills): ?>
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted mb-3">
                                            <i class="fas fa-folder me-2"></i><?php echo htmlspecialchars($category); ?>
                                            <span class="badge bg-secondary ms-2"><?php echo count($categorySkills); ?></span>
                                        </h6>
                                        <ul class="skill-tree">
                                            <?php foreach ($categorySkills as $skill): ?>
                                                <li class="skill-tree-item <?php echo $skill['parent_id'] ? 'child' : ''; ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong>
                                                            <?php if ($skill['parent_name']): ?>
                                                                <span class="text-muted ms-2">
                                                                    <i class="fas fa-arrow-right fa-xs"></i> 
                                                                    <?php echo htmlspecialchars($skill['parent_name']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php 
                                                            $synonyms = json_decode($skill['synonyms'], true);
                                                            if ($synonyms && count($synonyms) > 0): 
                                                            ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-tags me-1"></i>
                                                                    <?php echo implode(', ', array_slice($synonyms, 0, 3)); ?>
                                                                    <?php if (count($synonyms) > 3): ?>
                                                                        <span class="badge bg-light text-dark">+<?php echo count($synonyms) - 3; ?></span>
                                                                    <?php endif; ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="status-badge <?php echo $skill['is_active'] ? 'active' : 'inactive'; ?>">
                                                                <?php echo $skill['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                            <button class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editSkillModal<?php echo $skill['id']; ?>">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="toggle_skill">
                                                                <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                                                <button type="submit" class="btn-action toggle">
                                                                    <i class="fas fa-power-off"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this skill?')">
                                                                <input type="hidden" name="action" value="delete_skill">
                                                                <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                                                <button type="submit" class="btn-action delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </li>

                                                <!-- Edit Skill Modal -->
                                                <div class="modal fade" id="editSkillModal<?php echo $skill['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Skill</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="edit_skill">
                                                                    <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Skill Name *</label>
                                                                        <input type="text" class="form-control" name="skill_name" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Category *</label>
                                                                        <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($skill['category']); ?>" required list="categoryList">
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Parent Skill</label>
                                                                        <select class="form-select" name="parent_id">
                                                                            <option value="">None (Root Level)</option>
                                                                            <?php foreach ($parentSkills as $parent): ?>
                                                                                <?php if ($parent['id'] != $skill['id']): ?>
                                                                                    <option value="<?php echo $parent['id']; ?>" <?php echo $skill['parent_id'] == $parent['id'] ? 'selected' : ''; ?>>
                                                                                        <?php echo htmlspecialchars($parent['skill_name']); ?>
                                                                                    </option>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Synonyms</label>
                                                                        <input type="text" class="form-control" name="synonyms" placeholder="Separate with commas" value="<?php echo htmlspecialchars(implode(', ', json_decode($skill['synonyms'] ?? '[]', true) ?: [])); ?>">
                                                                        <small class="text-muted">Alternative names for this skill</small>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-matching">Save Changes</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="matching-card animate-in animate-delay-2">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie"></i> Categories Overview</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <p class="text-muted text-center">No categories yet</p>
                            <?php else: ?>
                                <?php foreach ($groupedSkills as $category => $categorySkills): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="fw-bold"><?php echo htmlspecialchars($category); ?></span>
                                        <span class="category-badge"><?php echo count($categorySkills); ?> skills</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($currentTab === 'settings'): ?>
            <!-- Recommendation Settings Tab -->
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="matching-card animate-in">
                            <div class="card-header">
                                <h5><i class="fas fa-balance-scale"></i> Weight Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="settings-group">
                                    <div class="settings-group-title">
                                        <i class="fas fa-percentage"></i> Matching Weights
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Skill Weight</div>
                                            <div class="setting-desc">Importance of skill matching</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" class="setting-input" name="settings[skill_weight]" value="<?php echo $settings['skill_weight'] ?? 40; ?>" min="0" max="100">
                                            <span class="text-muted">%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Experience Weight</div>
                                            <div class="setting-desc">Importance of experience matching</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" class="setting-input" name="settings[experience_weight]" value="<?php echo $settings['experience_weight'] ?? 25; ?>" min="0" max="100">
                                            <span class="text-muted">%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Education Weight</div>
                                            <div class="setting-desc">Importance of education matching</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" class="setting-input" name="settings[education_weight]" value="<?php echo $settings['education_weight'] ?? 20; ?>" min="0" max="100">
                                            <span class="text-muted">%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Location Weight</div>
                                            <div class="setting-desc">Importance of location matching</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" class="setting-input" name="settings[location_weight]" value="<?php echo $settings['location_weight'] ?? 15; ?>" min="0" max="100">
                                            <span class="text-muted">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="matching-card animate-in animate-delay-2">
                            <div class="card-header">
                                <h5><i class="fas fa-cogs"></i> General Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="settings-group">
                                    <div class="settings-group-title">
                                        <i class="fas fa-sliders-h"></i> Recommendation Parameters
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Minimum Match Score</div>
                                            <div class="setting-desc">Only show jobs above this threshold</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" class="setting-input" name="settings[min_match_score]" value="<?php echo $settings['min_match_score'] ?? 60; ?>" min="0" max="100">
                                            <span class="text-muted">%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Max Recommendations</div>
                                            <div class="setting-desc">Maximum jobs to recommend per user</div>
                                        </div>
                                        <input type="number" class="setting-input" name="settings[max_recommendations]" value="<?php echo $settings['max_recommendations'] ?? 10; ?>" min="1" max="50">
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Refresh Interval</div>
                                            <div class="setting-desc">Hours between recommendation updates</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" class="setting-input" name="settings[refresh_interval]" value="<?php echo $settings['refresh_interval'] ?? 24; ?>" min="1" max="168">
                                            <span class="text-muted">hrs</span>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-item">
                                        <div>
                                            <div class="setting-label">Algorithm Type</div>
                                            <div class="setting-desc">Matching algorithm to use</div>
                                        </div>
                                        <select class="form-select form-select--narrow" name="settings[match_algorithm]">
                                            <option value="weighted" <?php echo ($settings['match_algorithm'] ?? 'weighted') === 'weighted' ? 'selected' : ''; ?>>Weighted</option>
                                            <option value="ai" <?php echo ($settings['match_algorithm'] ?? '') === 'ai' ? 'selected' : ''; ?>>AI-Powered</option>
                                            <option value="hybrid" <?php echo ($settings['match_algorithm'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="matching-card animate-in animate-delay-3">
                            <div class="card-header">
                                <h5><i class="fas fa-toggle-on"></i> Feature Toggles</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="settings-group">
                                            <div class="settings-group-title">
                                                <i class="fas fa-robot"></i> AI Features
                                            </div>
                                            
                                            <div class="setting-item">
                                                <div>
                                                    <div class="setting-label">Enable AI Matching</div>
                                                    <div class="setting-desc">Use AI-powered job matching</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="hidden" name="settings[enable_ai_matching]" value="0">
                                                    <input type="checkbox" name="settings[enable_ai_matching]" value="1" <?php echo ($settings['enable_ai_matching'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="setting-item">
                                                <div>
                                                    <div class="setting-label">Consider Salary Range</div>
                                                    <div class="setting-desc">Include salary in matching</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="hidden" name="settings[consider_salary_range]" value="0">
                                                    <input type="checkbox" name="settings[consider_salary_range]" value="1" <?php echo ($settings['consider_salary_range'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="settings-group">
                                            <div class="settings-group-title">
                                                <i class="fas fa-bell"></i> Notifications & Priority
                                            </div>
                                            
                                            <div class="setting-item">
                                                <div>
                                                    <div class="setting-label">Email Notifications</div>
                                                    <div class="setting-desc">Send emails for new matches</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="hidden" name="settings[enable_email_notifications]" value="0">
                                                    <input type="checkbox" name="settings[enable_email_notifications]" value="1" <?php echo ($settings['enable_email_notifications'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            
                                            <div class="setting-item">
                                                <div>
                                                    <div class="setting-label">Boost Premium Jobs</div>
                                                    <div class="setting-desc">Prioritize premium job postings</div>
                                                </div>
                                                <label class="toggle-switch">
                                                    <input type="hidden" name="settings[boost_premium_jobs]" value="0">
                                                    <input type="checkbox" name="settings[boost_premium_jobs]" value="1" <?php echo ($settings['boost_premium_jobs'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-matching btn-lg">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Rule Modal -->
    <div class="modal fade" id="addRuleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Matching Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_rule">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Rule Name *</label>
                            <input type="text" class="form-control" name="rule_name" placeholder="e.g., Primary Skill Match" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Rule Type *</label>
                            <select class="form-select" name="rule_type" required>
                                <option value="skill">Skill</option>
                                <option value="experience">Experience</option>
                                <option value="education">Education</option>
                                <option value="location">Location</option>
                                <option value="salary">Salary</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Weight (0.00 - 1.00) *</label>
                            <input type="number" class="form-control" name="weight" min="0" max="1" step="0.01" value="0.50" required>
                            <small class="text-muted">Higher weight = more important in matching</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Optional description of this rule"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_required" id="isRequired">
                            <label class="form-check-label" for="isRequired">This rule is required for matching</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-matching">Add Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Skill Modal -->
    <div class="modal fade" id="addSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Skill to Taxonomy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_skill">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Skill Name *</label>
                            <input type="text" class="form-control" name="skill_name" placeholder="e.g., JavaScript" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Category *</label>
                            <input type="text" class="form-control" name="category" placeholder="e.g., Programming" required list="categoryList">
                            <datalist id="categoryList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                                <option value="Programming">
                                <option value="Design">
                                <option value="Marketing">
                                <option value="Management">
                                <option value="Communication">
                                <option value="Technical">
                                <option value="Soft Skills">
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Parent Skill</label>
                            <select class="form-select" name="parent_id">
                                <option value="">None (Root Level)</option>
                                <?php foreach ($parentSkills as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['skill_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Optional: Select if this is a sub-skill</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Synonyms</label>
                            <input type="text" class="form-control" name="synonyms" placeholder="JS, ECMAScript (comma separated)">
                            <small class="text-muted">Alternative names for this skill</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-matching">Add Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Weight validation
        document.querySelectorAll('input[name="settings[skill_weight]"], input[name="settings[experience_weight]"], input[name="settings[education_weight]"], input[name="settings[location_weight]"]').forEach(input => {
            input.addEventListener('change', function() {
                const skillWeight = parseInt(document.querySelector('input[name="settings[skill_weight]"]').value) || 0;
                const expWeight = parseInt(document.querySelector('input[name="settings[experience_weight]"]').value) || 0;
                const eduWeight = parseInt(document.querySelector('input[name="settings[education_weight]"]').value) || 0;
                const locWeight = parseInt(document.querySelector('input[name="settings[location_weight]"]').value) || 0;
                
                const total = skillWeight + expWeight + eduWeight + locWeight;
                
                if (total !== 100) {
                    console.warn('Weights should total 100%. Current total: ' + total + '%');
                }
            });
        });
    </script>
</body>
</html>

