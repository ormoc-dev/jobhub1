<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Note: Tables (announcements, faqs, career_resources, system_pages) 
// are defined in sql/all_additional_tables.sql

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Announcements
    if ($action === 'add_announcement') {
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content'];
        $type = $_POST['type'];
        $target = $_POST['target_audience'];
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, type, target_audience, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $type, $target, $start_date, $end_date, $_SESSION['user_id']]);
        $message = 'Announcement created successfully!';
        
    } elseif ($action === 'edit_announcement') {
        $id = (int)$_POST['id'];
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content'];
        $type = $_POST['type'];
        $target = $_POST['target_audience'];
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $stmt = $pdo->prepare("UPDATE announcements SET title=?, content=?, type=?, target_audience=?, start_date=?, end_date=? WHERE id=?");
        $stmt->execute([$title, $content, $type, $target, $start_date, $end_date, $id]);
        $message = 'Announcement updated successfully!';
        
    } elseif ($action === 'toggle_announcement') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Announcement status updated!';
        
    } elseif ($action === 'delete_announcement') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        $message = 'Announcement deleted!';
    }
    
    // FAQs
    elseif ($action === 'add_faq') {
        $question = sanitizeInput($_POST['question']);
        $answer = $_POST['answer'];
        $category = $_POST['category'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, category, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$question, $answer, $category, $sort_order]);
        $message = 'FAQ added successfully!';
        
    } elseif ($action === 'edit_faq') {
        $id = (int)$_POST['id'];
        $question = sanitizeInput($_POST['question']);
        $answer = $_POST['answer'];
        $category = $_POST['category'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE faqs SET question=?, answer=?, category=?, sort_order=? WHERE id=?");
        $stmt->execute([$question, $answer, $category, $sort_order, $id]);
        $message = 'FAQ updated successfully!';
        
    } elseif ($action === 'toggle_faq') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE faqs SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'FAQ status updated!';
        
    } elseif ($action === 'delete_faq') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM faqs WHERE id = ?")->execute([$id]);
        $message = 'FAQ deleted!';
    }
    
    // Career Resources
    elseif ($action === 'add_resource') {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $content = $_POST['content'];
        $category = $_POST['category'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // Handle image upload
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = '../uploads/resources/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image = 'uploads/resources/' . $filename;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO career_resources (title, description, content, category, image, is_featured) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $content, $category, $image, $is_featured]);
        $message = 'Career resource added successfully!';
        
    } elseif ($action === 'edit_resource') {
        $id = (int)$_POST['id'];
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $content = $_POST['content'];
        $category = $_POST['category'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // Handle image upload
        $image_sql = "";
        $params = [$title, $description, $content, $category, $is_featured];
        
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = '../uploads/resources/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_sql = ", image=?";
                $params[] = 'uploads/resources/' . $filename;
            }
        }
        $params[] = $id;
        
        $stmt = $pdo->prepare("UPDATE career_resources SET title=?, description=?, content=?, category=?, is_featured=? $image_sql WHERE id=?");
        $stmt->execute($params);
        $message = 'Career resource updated successfully!';
        
    } elseif ($action === 'toggle_resource') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE career_resources SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Resource status updated!';
        
    } elseif ($action === 'delete_resource') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM career_resources WHERE id = ?")->execute([$id]);
        $message = 'Resource deleted!';
    }
    
    // System Pages
    elseif ($action === 'add_page') {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['slug']));
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content'];
        $meta = sanitizeInput($_POST['meta_description']);
        
        $stmt = $pdo->prepare("INSERT INTO system_pages (slug, title, content, meta_description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$slug, $title, $content, $meta]);
        $message = 'System page created successfully!';
        
    } elseif ($action === 'edit_page') {
        $id = (int)$_POST['id'];
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['slug']));
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content'];
        $meta = sanitizeInput($_POST['meta_description']);
        
        $stmt = $pdo->prepare("UPDATE system_pages SET slug=?, title=?, content=?, meta_description=? WHERE id=?");
        $stmt->execute([$slug, $title, $content, $meta, $id]);
        $message = 'System page updated successfully!';
        
    } elseif ($action === 'toggle_page') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE system_pages SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $message = 'Page status updated!';
        
    } elseif ($action === 'delete_page') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM system_pages WHERE id = ?")->execute([$id]);
        $message = 'Page deleted!';
    }
}

// Get current tab
$currentTab = $_GET['tab'] ?? 'announcements';

// Fetch data
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
$faqs = $pdo->query("SELECT * FROM faqs ORDER BY category, sort_order, created_at DESC")->fetchAll();
$resources = $pdo->query("SELECT * FROM career_resources ORDER BY created_at DESC")->fetchAll();
$pages = $pdo->query("SELECT * FROM system_pages ORDER BY title")->fetchAll();

// Stats
$stats = [
    'announcements' => count($announcements),
    'active_announcements' => count(array_filter($announcements, fn($a) => $a['is_active'])),
    'faqs' => count($faqs),
    'active_faqs' => count(array_filter($faqs, fn($f) => $f['is_active'])),
    'resources' => count($resources),
    'featured_resources' => count(array_filter($resources, fn($r) => $r['is_featured'])),
    'pages' => count($pages),
    'active_pages' => count(array_filter($pages, fn($p) => $p['is_active']))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --coral: #f97316;
            --coral-light: #fb923c;
            --coral-dark: #ea580c;
            --teal: #14b8a6;
            --teal-light: #2dd4bf;
            --purple: #8b5cf6;
            --rose: #f43f5e;
            --amber: #f59e0b;
            --sky: #0ea5e9;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-100: #f1f5f9;
        }
        
        .content-page {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
        }
        
        .content-page .admin-main-content {
            background: transparent;
            padding: 24px 32px;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .page-header h1 {
            font-weight: 800;
            font-size: 1.85rem;
            color: #fff;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .page-header h1 .icon-wrapper {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--coral), var(--coral-light));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 24px rgba(249, 115, 22, 0.3);
        }
        
        .page-header p {
            color: var(--slate-400);
            margin: 0;
            font-size: 0.95rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }
        
        .stat-card {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 18px;
            padding: 22px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.1;
            transition: all 0.4s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            border-color: rgba(255,255,255,0.1);
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
        }
        
        .stat-card:hover::before {
            transform: scale(1.5);
            opacity: 0.15;
        }
        
        .stat-card.announcements::before { background: var(--coral); }
        .stat-card.faqs::before { background: var(--teal); }
        .stat-card.resources::before { background: var(--purple); }
        .stat-card.pages::before { background: var(--sky); }
        
        .stat-card .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        
        .stat-card.announcements .stat-icon { background: linear-gradient(135deg, var(--coral), var(--coral-light)); color: white; }
        .stat-card.faqs .stat-icon { background: linear-gradient(135deg, var(--teal), var(--teal-light)); color: white; }
        .stat-card.resources .stat-icon { background: linear-gradient(135deg, var(--purple), #a78bfa); color: white; }
        .stat-card.pages .stat-icon { background: linear-gradient(135deg, var(--sky), #38bdf8); color: white; }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
            line-height: 1;
        }
        
        .stat-card .stat-label {
            color: var(--slate-400);
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 4px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: var(--slate-500);
            margin-top: 8px;
        }
        
        .stat-card .stat-sub span {
            color: #4ade80;
            font-weight: 600;
        }
        
        /* Alert */
        .alert-custom {
            border: none;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(16, 185, 129, 0.12);
            border-left: 4px solid #10b981;
            color: #6ee7b7;
        }
        
        /* Tab Navigation */
        .tab-nav {
            display: flex;
            gap: 6px;
            margin-bottom: 24px;
            background: var(--slate-800);
            padding: 6px;
            border-radius: 16px;
            width: fit-content;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .tab-link {
            padding: 14px 22px;
            border-radius: 12px;
            color: var(--slate-400);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tab-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }
        
        .tab-link.active {
            color: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .tab-link.active.announcements { background: linear-gradient(135deg, var(--coral), var(--coral-dark)); }
        .tab-link.active.faqs { background: linear-gradient(135deg, var(--teal), #0d9488); }
        .tab-link.active.resources { background: linear-gradient(135deg, var(--purple), #7c3aed); }
        .tab-link.active.pages { background: linear-gradient(135deg, var(--sky), #0284c7); }
        
        .tab-link .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
        }
        
        /* Main Card */
        .main-card {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .card-header-custom h5 {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header-custom h5 .count {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .announcements-section .count { background: rgba(249, 115, 22, 0.2); color: var(--coral-light); }
        .faqs-section .count { background: rgba(20, 184, 166, 0.2); color: var(--teal-light); }
        .resources-section .count { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .pages-section .count { background: rgba(14, 165, 233, 0.2); color: #38bdf8; }
        
        .btn-add {
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            color: white;
        }
        
        .announcements-section .btn-add { background: linear-gradient(135deg, var(--coral), var(--coral-dark)); }
        .faqs-section .btn-add { background: linear-gradient(135deg, var(--teal), #0d9488); }
        .resources-section .btn-add { background: linear-gradient(135deg, var(--purple), #7c3aed); }
        .pages-section .btn-add { background: linear-gradient(135deg, var(--sky), #0284c7); }
        
        /* Content Items */
        .content-list {
            padding: 16px;
        }
        
        .content-item {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .content-item:hover {
            border-color: rgba(255,255,255,0.1);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .content-item:last-child { margin-bottom: 0; }
        
        .content-item .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 10px;
        }
        
        .content-item h6 {
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            flex: 1;
        }
        
        .content-item .item-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .content-item .item-preview {
            color: var(--slate-400);
            font-size: 0.85rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Badges */
        .badge-type {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-type.info { background: rgba(14, 165, 233, 0.15); color: #38bdf8; }
        .badge-type.warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .badge-type.success { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .badge-type.urgent { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        
        .badge-category {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-category.general { background: rgba(100, 116, 139, 0.2); color: var(--slate-300); }
        .badge-category.employers { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        .badge-category.jobseekers { background: rgba(20, 184, 166, 0.15); color: var(--teal-light); }
        .badge-category.account { background: rgba(14, 165, 233, 0.15); color: #38bdf8; }
        .badge-category.technical { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .badge-category.resume { background: rgba(249, 115, 22, 0.15); color: var(--coral-light); }
        .badge-category.interview { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
        .badge-category.career { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .badge-category.skills { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
        .badge-category.workplace { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-status.active { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .badge-status.inactive { background: rgba(107, 114, 128, 0.15); color: #9ca3af; }
        .badge-status.featured { background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(168, 85, 247, 0.2)); color: #c084fc; }
        
        .badge-audience {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.05);
            color: var(--slate-300);
        }
        
        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
        }
        
        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-action:hover { transform: translateY(-2px); }
        
        .btn-edit { background: rgba(14, 165, 233, 0.15); color: #38bdf8; }
        .btn-edit:hover { background: rgba(14, 165, 233, 0.25); color: #38bdf8; }
        
        .btn-toggle { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .btn-toggle:hover { background: rgba(245, 158, 11, 0.25); color: #fbbf24; }
        
        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.25); color: #f87171; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 56px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .announcements-section .empty-state i { color: var(--coral); }
        .faqs-section .empty-state i { color: var(--teal); }
        .resources-section .empty-state i { color: var(--purple); }
        .pages-section .empty-state i { color: var(--sky); }
        
        .empty-state h5 {
            color: var(--slate-300);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--slate-500);
            font-size: 0.9rem;
        }
        
        /* Modal Styles */
        .modal-content {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
        }
        
        .modal-header {
            border-radius: 20px 20px 0 0;
            padding: 20px 24px;
            border: none;
        }
        
        .announcements-section .modal-header { background: linear-gradient(135deg, var(--coral), var(--coral-dark)); }
        .faqs-section .modal-header { background: linear-gradient(135deg, var(--teal), #0d9488); }
        .resources-section .modal-header { background: linear-gradient(135deg, var(--purple), #7c3aed); }
        .pages-section .modal-header { background: linear-gradient(135deg, var(--sky), #0284c7); }
        
        .modal-title {
            color: white;
            font-weight: 700;
        }
        
        .modal-body {
            padding: 24px;
            color: var(--slate-300);
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        .form-label {
            color: var(--slate-300);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            background: var(--slate-900);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            padding: 12px 16px;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--slate-900);
            border-color: var(--coral);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
            color: #fff;
        }
        
        .form-control::placeholder { color: var(--slate-500); }
        
        .form-select option {
            background: var(--slate-800);
            color: #fff;
        }
        
        .btn-modal-cancel {
            background: var(--slate-700);
            color: var(--slate-300);
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-modal-cancel:hover {
            background: var(--slate-600);
            color: #fff;
        }
        
        .btn-modal-save {
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .announcements-section .btn-modal-save { background: linear-gradient(135deg, var(--coral), var(--coral-dark)); }
        .faqs-section .btn-modal-save { background: linear-gradient(135deg, var(--teal), #0d9488); }
        .resources-section .btn-modal-save { background: linear-gradient(135deg, var(--purple), #7c3aed); }
        .pages-section .btn-modal-save { background: linear-gradient(135deg, var(--sky), #0284c7); }
        
        .btn-modal-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            color: white;
        }
        
        /* Resource Card */
        .resource-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .resource-card:hover {
            border-color: rgba(255,255,255,0.1);
            transform: translateY(-4px);
        }
        
        .resource-card .resource-img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--slate-700), var(--slate-800));
        }
        
        .resource-card .resource-img-placeholder {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, var(--purple), #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: rgba(255,255,255,0.3);
        }
        
        .resource-card .resource-body {
            padding: 16px;
        }
        
        .resource-card h6 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .resource-card .resource-desc {
            color: var(--slate-400);
            font-size: 0.8rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12px;
        }
        
        /* Page Item */
        .page-item {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .page-item:hover {
            border-color: rgba(255,255,255,0.1);
        }
        
        .page-item .page-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--sky), #0284c7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .page-item .page-info {
            flex: 1;
        }
        
        .page-item h6 {
            color: #fff;
            font-weight: 600;
            margin: 0 0 4px 0;
        }
        
        .page-item .page-slug {
            color: var(--slate-500);
            font-size: 0.8rem;
            font-family: monospace;
        }
        
        /* Form Check */
        .form-check-input {
            background-color: var(--slate-700);
            border-color: var(--slate-600);
        }
        
        .form-check-input:checked {
            background-color: var(--coral);
            border-color: var(--coral);
        }
        
        .form-check-label {
            color: var(--slate-300);
        }
        
        /* Date display */
        .date-info {
            font-size: 0.75rem;
            color: var(--slate-500);
        }
        
        .date-info i {
            margin-right: 4px;
        }
    </style>
</head>
<body class="admin-layout content-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>
                    <span class="icon-wrapper"><i class="fas fa-bullhorn"></i></span>
                    Content Management
                </h1>
                <p>Manage announcements, FAQs, career resources, and system pages</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert-custom">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card announcements">
                <div class="stat-icon"><i class="fas fa-bullhorn"></i></div>
                <h3><?php echo $stats['announcements']; ?></h3>
                <div class="stat-label">Announcements</div>
                <div class="stat-sub"><span><?php echo $stats['active_announcements']; ?></span> currently active</div>
            </div>
            <div class="stat-card faqs">
                <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
                <h3><?php echo $stats['faqs']; ?></h3>
                <div class="stat-label">FAQs</div>
                <div class="stat-sub"><span><?php echo $stats['active_faqs']; ?></span> published</div>
            </div>
            <div class="stat-card resources">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3><?php echo $stats['resources']; ?></h3>
                <div class="stat-label">Career Resources</div>
                <div class="stat-sub"><span><?php echo $stats['featured_resources']; ?></span> featured</div>
            </div>
            <div class="stat-card pages">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <h3><?php echo $stats['pages']; ?></h3>
                <div class="stat-label">System Pages</div>
                <div class="stat-sub"><span><?php echo $stats['active_pages']; ?></span> published</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="?tab=announcements" class="tab-link announcements <?php echo $currentTab === 'announcements' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> Announcements
                <span class="badge"><?php echo $stats['announcements']; ?></span>
            </a>
            <a href="?tab=faqs" class="tab-link faqs <?php echo $currentTab === 'faqs' ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i> FAQs
                <span class="badge"><?php echo $stats['faqs']; ?></span>
            </a>
            <a href="?tab=resources" class="tab-link resources <?php echo $currentTab === 'resources' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i> Career Resources
                <span class="badge"><?php echo $stats['resources']; ?></span>
            </a>
            <a href="?tab=pages" class="tab-link pages <?php echo $currentTab === 'pages' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> System Pages
                <span class="badge"><?php echo $stats['pages']; ?></span>
            </a>
        </div>

        <!-- Announcements Section -->
        <?php if ($currentTab === 'announcements'): ?>
        <div class="main-card announcements-section">
            <div class="card-header-custom">
                <h5>
                    <i class="fas fa-bullhorn" style="color: var(--coral);"></i>
                    All Announcements
                    <span class="count"><?php echo count($announcements); ?></span>
                </h5>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="fas fa-plus"></i> New Announcement
                </button>
            </div>
            
            <div class="content-list">
                <?php if (empty($announcements)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h5>No Announcements Yet</h5>
                        <p>Create your first announcement to communicate with users.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $item): ?>
                        <div class="content-item">
                            <div class="item-header">
                                <div>
                                    <h6><?php echo htmlspecialchars($item['title']); ?></h6>
                                    <div class="item-meta mt-2">
                                        <span class="badge-type <?php echo $item['type']; ?>"><?php echo ucfirst($item['type']); ?></span>
                                        <span class="badge-audience"><i class="fas fa-users me-1"></i><?php echo ucfirst($item['target_audience']); ?></span>
                                        <span class="badge-status <?php echo $item['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <span class="date-info"><i class="fas fa-calendar"></i><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="action-btns">
                                    <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?php echo $item['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_announcement">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-action btn-toggle"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                                        <input type="hidden" name="action" value="delete_announcement">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <p class="item-preview"><?php echo htmlspecialchars(strip_tags($item['content'])); ?></p>
                        </div>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editAnnouncementModal<?php echo $item['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Announcement</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit_announcement">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Content</label>
                                                <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($item['content']); ?></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Type</label>
                                                    <select name="type" class="form-select">
                                                        <option value="info" <?php echo $item['type'] === 'info' ? 'selected' : ''; ?>>Info</option>
                                                        <option value="warning" <?php echo $item['type'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                                        <option value="success" <?php echo $item['type'] === 'success' ? 'selected' : ''; ?>>Success</option>
                                                        <option value="urgent" <?php echo $item['type'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Target Audience</label>
                                                    <select name="target_audience" class="form-select">
                                                        <option value="all" <?php echo $item['target_audience'] === 'all' ? 'selected' : ''; ?>>All Users</option>
                                                        <option value="employers" <?php echo $item['target_audience'] === 'employers' ? 'selected' : ''; ?>>Employers Only</option>
                                                        <option value="employees" <?php echo $item['target_audience'] === 'employees' ? 'selected' : ''; ?>>Job Seekers Only</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Start Date (Optional)</label>
                                                    <input type="date" name="start_date" class="form-control" value="<?php echo $item['start_date']; ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">End Date (Optional)</label>
                                                    <input type="date" name="end_date" class="form-control" value="<?php echo $item['end_date']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modal-save">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Announcement Modal -->
        <div class="modal fade announcements-section" id="addAnnouncementModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>New Announcement</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_announcement">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" placeholder="Enter announcement title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Content</label>
                                <textarea name="content" class="form-control" rows="5" placeholder="Write your announcement here..." required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-select">
                                        <option value="info">Info</option>
                                        <option value="warning">Warning</option>
                                        <option value="success">Success</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Target Audience</label>
                                    <select name="target_audience" class="form-select">
                                        <option value="all">All Users</option>
                                        <option value="employers">Employers Only</option>
                                        <option value="employees">Job Seekers Only</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Start Date (Optional)</label>
                                    <input type="date" name="start_date" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">End Date (Optional)</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-modal-save">Create Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FAQs Section -->
        <?php if ($currentTab === 'faqs'): ?>
        <div class="main-card faqs-section">
            <div class="card-header-custom">
                <h5>
                    <i class="fas fa-question-circle" style="color: var(--teal);"></i>
                    Frequently Asked Questions
                    <span class="count"><?php echo count($faqs); ?></span>
                </h5>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addFaqModal">
                    <i class="fas fa-plus"></i> Add FAQ
                </button>
            </div>
            
            <div class="content-list">
                <?php if (empty($faqs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-question-circle"></i>
                        <h5>No FAQs Yet</h5>
                        <p>Add frequently asked questions to help your users.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($faqs as $item): ?>
                        <div class="content-item">
                            <div class="item-header">
                                <div>
                                    <h6><?php echo htmlspecialchars($item['question']); ?></h6>
                                    <div class="item-meta mt-2">
                                        <span class="badge-category <?php echo $item['category']; ?>"><?php echo ucfirst($item['category']); ?></span>
                                        <span class="badge-status <?php echo $item['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $item['is_active'] ? 'Published' : 'Draft'; ?>
                                        </span>
                                        <span class="date-info"><i class="fas fa-sort-numeric-down"></i>Order: <?php echo $item['sort_order']; ?></span>
                                    </div>
                                </div>
                                <div class="action-btns">
                                    <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editFaqModal<?php echo $item['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_faq">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-action btn-toggle"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this FAQ?')">
                                        <input type="hidden" name="action" value="delete_faq">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <p class="item-preview"><?php echo htmlspecialchars(strip_tags($item['answer'])); ?></p>
                        </div>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editFaqModal<?php echo $item['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit FAQ</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit_faq">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Question</label>
                                                <input type="text" name="question" class="form-control" value="<?php echo htmlspecialchars($item['question']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Answer</label>
                                                <textarea name="answer" class="form-control" rows="5" required><?php echo htmlspecialchars($item['answer']); ?></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Category</label>
                                                    <select name="category" class="form-select">
                                                        <option value="general" <?php echo $item['category'] === 'general' ? 'selected' : ''; ?>>General</option>
                                                        <option value="employers" <?php echo $item['category'] === 'employers' ? 'selected' : ''; ?>>For Employers</option>
                                                        <option value="jobseekers" <?php echo $item['category'] === 'jobseekers' ? 'selected' : ''; ?>>For Job Seekers</option>
                                                        <option value="account" <?php echo $item['category'] === 'account' ? 'selected' : ''; ?>>Account & Profile</option>
                                                        <option value="technical" <?php echo $item['category'] === 'technical' ? 'selected' : ''; ?>>Technical Support</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Sort Order</label>
                                                    <input type="number" name="sort_order" class="form-control" value="<?php echo $item['sort_order']; ?>" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modal-save">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add FAQ Modal -->
        <div class="modal fade faqs-section" id="addFaqModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New FAQ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_faq">
                            <div class="mb-3">
                                <label class="form-label">Question</label>
                                <input type="text" name="question" class="form-control" placeholder="What is the question?" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Answer</label>
                                <textarea name="answer" class="form-control" rows="5" placeholder="Provide a helpful answer..." required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="general">General</option>
                                        <option value="employers">For Employers</option>
                                        <option value="jobseekers">For Job Seekers</option>
                                        <option value="account">Account & Profile</option>
                                        <option value="technical">Technical Support</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-modal-save">Add FAQ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Career Resources Section -->
        <?php if ($currentTab === 'resources'): ?>
        <div class="main-card resources-section">
            <div class="card-header-custom">
                <h5>
                    <i class="fas fa-graduation-cap" style="color: var(--purple);"></i>
                    Career Resources
                    <span class="count"><?php echo count($resources); ?></span>
                </h5>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                    <i class="fas fa-plus"></i> Add Resource
                </button>
            </div>
            
            <div class="content-list">
                <?php if (empty($resources)): ?>
                    <div class="empty-state">
                        <i class="fas fa-graduation-cap"></i>
                        <h5>No Career Resources Yet</h5>
                        <p>Add helpful articles and guides for job seekers.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($resources as $item): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="resource-card">
                                    <?php if ($item['image']): ?>
                                        <img src="../<?php echo htmlspecialchars($item['image']); ?>" class="resource-img" alt="">
                                    <?php else: ?>
                                        <div class="resource-img-placeholder"><i class="fas fa-book-open"></i></div>
                                    <?php endif; ?>
                                    <div class="resource-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge-category <?php echo $item['category']; ?>"><?php echo ucfirst($item['category']); ?></span>
                                            <?php if ($item['is_featured']): ?>
                                                <span class="badge-status featured"><i class="fas fa-star me-1"></i>Featured</span>
                                            <?php endif; ?>
                                        </div>
                                        <h6><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <p class="resource-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="date-info"><i class="fas fa-eye"></i><?php echo $item['views']; ?> views</span>
                                            <div class="action-btns">
                                                <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editResourceModal<?php echo $item['id']; ?>"><i class="fas fa-edit"></i></button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_resource">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn-action btn-toggle"><i class="fas fa-power-off"></i></button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this resource?')">
                                                    <input type="hidden" name="action" value="delete_resource">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editResourceModal<?php echo $item['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Resource</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit_resource">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($item['description']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Content</label>
                                                    <textarea name="content" class="form-control" rows="6" required><?php echo htmlspecialchars($item['content']); ?></textarea>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Category</label>
                                                        <select name="category" class="form-select">
                                                            <option value="resume" <?php echo $item['category'] === 'resume' ? 'selected' : ''; ?>>Resume Tips</option>
                                                            <option value="interview" <?php echo $item['category'] === 'interview' ? 'selected' : ''; ?>>Interview Prep</option>
                                                            <option value="career" <?php echo $item['category'] === 'career' ? 'selected' : ''; ?>>Career Advice</option>
                                                            <option value="skills" <?php echo $item['category'] === 'skills' ? 'selected' : ''; ?>>Skill Development</option>
                                                            <option value="workplace" <?php echo $item['category'] === 'workplace' ? 'selected' : ''; ?>>Workplace Tips</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Image</label>
                                                        <input type="file" name="image" class="form-control" accept="image/*">
                                                    </div>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_featured" id="featured<?php echo $item['id']; ?>" <?php echo $item['is_featured'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="featured<?php echo $item['id']; ?>">Feature this resource</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn-modal-save">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Resource Modal -->
        <div class="modal fade resources-section" id="addResourceModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Career Resource</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_resource">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" placeholder="Resource title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Content</label>
                                <textarea name="content" class="form-control" rows="6" placeholder="Full content..." required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="resume">Resume Tips</option>
                                        <option value="interview">Interview Prep</option>
                                        <option value="career">Career Advice</option>
                                        <option value="skills">Skill Development</option>
                                        <option value="workplace">Workplace Tips</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Image</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="featuredNew">
                                <label class="form-check-label" for="featuredNew">Feature this resource</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-modal-save">Add Resource</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Pages Section -->
        <?php if ($currentTab === 'pages'): ?>
        <div class="main-card pages-section">
            <div class="card-header-custom">
                <h5>
                    <i class="fas fa-file-alt" style="color: var(--sky);"></i>
                    System Pages
                    <span class="count"><?php echo count($pages); ?></span>
                </h5>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addPageModal">
                    <i class="fas fa-plus"></i> New Page
                </button>
            </div>
            
            <div class="content-list">
                <?php if (empty($pages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h5>No System Pages Yet</h5>
                        <p>Create pages like Terms of Service, Privacy Policy, etc.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pages as $item): ?>
                        <div class="page-item">
                            <div class="page-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="page-info">
                                <h6><?php echo htmlspecialchars($item['title']); ?></h6>
                                <span class="page-slug">/page/<?php echo htmlspecialchars($item['slug']); ?></span>
                            </div>
                            <span class="badge-status <?php echo $item['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $item['is_active'] ? 'Published' : 'Draft'; ?>
                            </span>
                            <div class="action-btns">
                                <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editPageModal<?php echo $item['id']; ?>"><i class="fas fa-edit"></i></button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_page">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-action btn-toggle"><i class="fas fa-power-off"></i></button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this page?')">
                                    <input type="hidden" name="action" value="delete_page">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editPageModal<?php echo $item['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Page</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit_page">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <div class="row">
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label">Page Title</label>
                                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">URL Slug</label>
                                                    <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($item['slug']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meta Description (SEO)</label>
                                                <textarea name="meta_description" class="form-control" rows="2"><?php echo htmlspecialchars($item['meta_description']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Page Content</label>
                                                <textarea name="content" class="form-control" rows="12" required><?php echo htmlspecialchars($item['content']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modal-save">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Page Modal -->
        <div class="modal fade pages-section" id="addPageModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Page</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_page">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Page Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g., Terms of Service" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">URL Slug</label>
                                    <input type="text" name="slug" class="form-control" placeholder="e.g., terms-of-service" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Meta Description (SEO)</label>
                                <textarea name="meta_description" class="form-control" rows="2" placeholder="Brief description for search engines..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Page Content</label>
                                <textarea name="content" class="form-control" rows="12" placeholder="Write your page content here..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-modal-save">Create Page</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

