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
.content-admin-page .admin-main-content {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 2rem 2.5rem;
        }

        .content-page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #fff7ed 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .content-page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .content-page-header h1 .icon-wrapper {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%);
            border: 1px solid rgba(234, 88, 12, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #ea580c;
            box-shadow: 0 2px 8px rgba(234, 88, 12, 0.12);
        }

        .content-page-header p {
            color: #64748b;
            margin: 0;
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }

        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.2s, border-color 0.2s;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .stat-card:hover {
            border-color: rgba(37, 99, 235, 0.2);
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.08);
        }

        .stat-card .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 14px;
        }

        .stat-card.announcements .stat-icon { background: #ffedd5; color: #c2410c; }
        .stat-card.faqs .stat-icon { background: #ccfbf1; color: #0f766e; }
        .stat-card.resources .stat-icon { background: #ede9fe; color: #6d28d9; }
        .stat-card.pages .stat-icon { background: #e0f2fe; color: #0369a1; }

        .stat-card h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            line-height: 1.1;
        }

        .stat-card .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 4px;
        }

        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 8px;
        }

        .stat-card .stat-sub span {
            color: #047857;
            font-weight: 600;
        }

        .alert-custom {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .tab-nav {
            display: flex;
            gap: 6px;
            margin-bottom: 1.25rem;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 12px;
            width: fit-content;
            max-width: 100%;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
        }

        .tab-link {
            padding: 10px 18px;
            border-radius: 10px;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-link:hover {
            color: #1e3a8a;
            background: rgba(255, 255, 255, 0.85);
        }

        .tab-link.active {
            color: #0f172a;
            background: #fff;
            box-shadow: 0 1px 4px rgba(30, 58, 138, 0.1);
        }

        .tab-link.active.announcements { color: #c2410c; background: #fff7ed; }
        .tab-link.active.faqs { color: #0f766e; background: #f0fdfa; }
        .tab-link.active.resources { color: #6d28d9; background: #f5f3ff; }
        .tab-link.active.pages { color: #0369a1; background: #f0f9ff; }

        .tab-link .badge {
            font-size: 0.68rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            background: #e2e8f0;
            color: #475569;
        }

        .tab-link.active .badge {
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
        }

        .main-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .card-header-custom {
            padding: 1.1rem 1.35rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            background: linear-gradient(120deg, #fafbff 0%, #f8fafc 100%);
        }

        .card-header-custom h5 {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 1.05rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .card-header-custom h5 .count {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .announcements-section .count { background: #ffedd5; color: #c2410c; }
        .faqs-section .count { background: #ccfbf1; color: #0f766e; }
        .resources-section .count { background: #ede9fe; color: #6d28d9; }
        .pages-section .count { background: #e0f2fe; color: #0369a1; }

        .card-header-icon--coral { color: #ea580c; }
        .card-header-icon--teal { color: #0d9488; }
        .card-header-icon--purple { color: #7c3aed; }
        .card-header-icon--sky { color: #0284c7; }

        .btn-add {
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            color: white;
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.15s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
            color: white;
        }

        .announcements-section .btn-add { background: linear-gradient(135deg, #ea580c, #c2410c); }
        .faqs-section .btn-add { background: linear-gradient(135deg, #0d9488, #0f766e); }
        .resources-section .btn-add { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .pages-section .btn-add { background: linear-gradient(135deg, #0284c7, #0369a1); }

        .content-list {
            padding: 14px;
        }

        .content-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .content-item:hover {
            border-color: rgba(37, 99, 235, 0.2);
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
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
            color: #0f172a;
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            flex: 1;
        }

        .content-item .item-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .content-item .item-preview {
            color: #64748b;
            font-size: 0.85rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .badge-type {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        .badge-type.info { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
        .badge-type.warning { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .badge-type.success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .badge-type.urgent { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .badge-category {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
            border: 1px solid #e2e8f0;
        }

        .badge-category.general { background: #f1f5f9; color: #475569; }
        .badge-category.employers { background: #ede9fe; color: #6d28d9; }
        .badge-category.jobseekers { background: #ccfbf1; color: #0f766e; }
        .badge-category.account { background: #e0f2fe; color: #0369a1; }
        .badge-category.technical { background: #fffbeb; color: #b45309; }
        .badge-category.resume { background: #ffedd5; color: #c2410c; }
        .badge-category.interview { background: #fce7f3; color: #be185d; }
        .badge-category.career { background: #ecfdf5; color: #047857; }
        .badge-category.skills { background: #eef2ff; color: #4338ca; }
        .badge-category.workplace { background: #f3e8ff; color: #7e22ce; }

        .badge-status {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
        }

        .badge-status.active { background: #ecfdf5; color: #047857; }
        .badge-status.inactive { background: #f1f5f9; color: #64748b; }
        .badge-status.featured { background: #f5f3ff; color: #6d28d9; }

        .badge-audience {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .action-btns {
            display: flex;
            gap: 6px;
        }

        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background: #fff;
            color: #0284c7;
            border-color: rgba(2, 132, 199, 0.35);
        }
        .btn-edit:hover { background: #e0f2fe; }

        .btn-toggle {
            background: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }
        .btn-toggle:hover { background: #fef3c7; }

        .btn-delete {
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .btn-delete:hover { background: #fef2f2; }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
        }

        .empty-state i {
            font-size: 52px;
            margin-bottom: 16px;
            opacity: 0.35;
        }

        .announcements-section .empty-state i { color: #ea580c; }
        .faqs-section .empty-state i { color: #0d9488; }
        .resources-section .empty-state i { color: #7c3aed; }
        .pages-section .empty-state i { color: #0284c7; }

        .empty-state h5 {
            color: #334155;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .content-admin-page .modal-content {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }

        .announcements-section .modal-header {
            background: linear-gradient(120deg, #fff7ed 0%, #ffedd5 100%);
            border-bottom: 1px solid #fed7aa;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .faqs-section .modal-header {
            background: linear-gradient(120deg, #f0fdfa 0%, #ccfbf1 100%);
            border-bottom: 1px solid #99f6e4;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .resources-section .modal-header {
            background: linear-gradient(120deg, #f5f3ff 0%, #ede9fe 100%);
            border-bottom: 1px solid #ddd6fe;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .pages-section .modal-header {
            background: linear-gradient(120deg, #f0f9ff 0%, #e0f2fe 100%);
            border-bottom: 1px solid #bae6fd;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .content-admin-page .modal-title {
            color: #1e3a8a;
            font-weight: 700;
        }

        .content-admin-page .modal-body {
            padding: 20px;
            color: #334155;
        }

        .content-admin-page .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid #e2e8f0;
            background: #fafafa;
        }

        .content-admin-page .form-label {
            color: #334155;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .content-admin-page .form-control,
        .content-admin-page .form-select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            padding: 10px 14px;
        }

        .content-admin-page .form-control:focus,
        .content-admin-page .form-select:focus {
            background: #fff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            color: #0f172a;
        }

        .content-admin-page .form-control::placeholder {
            color: #94a3b8;
        }

        .content-admin-page .form-select option {
            background: #fff;
            color: #0f172a;
        }

        .btn-modal-cancel {
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-modal-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .btn-modal-save {
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }

        .announcements-section .btn-modal-save { background: linear-gradient(135deg, #ea580c, #c2410c); }
        .faqs-section .btn-modal-save { background: linear-gradient(135deg, #0d9488, #0f766e); }
        .resources-section .btn-modal-save { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .pages-section .btn-modal-save { background: linear-gradient(135deg, #0284c7, #0369a1); }

        .btn-modal-save:hover {
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
            color: white;
        }

        .resource-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .resource-card:hover {
            border-color: rgba(124, 58, 237, 0.3);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
        }

        .resource-card .resource-img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background: #f1f5f9;
        }

        .resource-card .resource-img-placeholder {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: rgba(109, 40, 217, 0.35);
        }

        .resource-card .resource-body {
            padding: 16px;
        }

        .resource-card h6 {
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .resource-card .resource-desc {
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .page-item {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .page-item:hover {
            border-color: rgba(2, 132, 199, 0.35);
            background: #fff;
        }

        .page-item .page-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: #0369a1;
        }

        .page-item .page-info {
            flex: 1;
        }

        .page-item h6 {
            color: #0f172a;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .page-item .page-slug {
            color: #64748b;
            font-size: 0.8rem;
            font-family: ui-monospace, 'Consolas', monospace;
        }

        .content-admin-page .form-check-input {
            background-color: #fff;
            border-color: #cbd5e1;
        }

        .content-admin-page .form-check-input:checked {
            background-color: #ea580c;
            border-color: #ea580c;
        }

        .content-admin-page .form-check-label {
            color: #475569;
        }

        .date-info {
            font-size: 0.75rem;
            color: #64748b;
        }

        .date-info i {
            margin-right: 4px;
        }
    </style>
</head>
<body class="admin-layout content-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="content-page-header">
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
                    <i class="fas fa-bullhorn card-header-icon--coral"></i>
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
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <i class="fas fa-question-circle card-header-icon--teal"></i>
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
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <i class="fas fa-graduation-cap card-header-icon--purple"></i>
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
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <i class="fas fa-file-alt card-header-icon--sky"></i>
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
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

