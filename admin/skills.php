<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Handle Add Skill
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $skillName = sanitizeInput($_POST['skill_name']);
    $category = sanitizeInput($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    
    if (empty($skillName)) {
        $error = 'Skill name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO skills (skill_name, category, description) VALUES (?, ?, ?)");
            $stmt->execute([$skillName, $category, $description]);
            $message = 'Skill added successfully!';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Skill name already exists.';
            } else {
                $error = 'Error adding skill: ' . $e->getMessage();
            }
        }
    }
}

// Handle Edit Skill
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $skillId = (int)$_POST['skill_id'];
    $skillName = sanitizeInput($_POST['skill_name']);
    $category = sanitizeInput($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    $status = sanitizeInput($_POST['status']);
    
    if (empty($skillName)) {
        $error = 'Skill name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE skills SET skill_name = ?, category = ?, description = ?, status = ? WHERE id = ?");
            $stmt->execute([$skillName, $category, $description, $status, $skillId]);
            $message = 'Skill updated successfully!';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Skill name already exists.';
            } else {
                $error = 'Error updating skill: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete Skill
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $skillId = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM skills WHERE id = ?");
        $stmt->execute([$skillId]);
        $message = 'Skill deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Error deleting skill: ' . $e->getMessage();
    }
}

// Get all skills
$skills = $pdo->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();

// Get unique categories for dropdown
$categories = $pdo->query("SELECT DISTINCT category FROM skills WHERE status = 'active' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills Management - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .skills-admin-page .admin-main-content {
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 2rem 2.5rem;
        }

        .skills-page-header {
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .skills-page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
        }

        .skills-page-header h1 i {
            color: #2563eb;
            opacity: 0.9;
        }

        .skills-page-header .text-muted {
            color: #64748b !important;
        }

        .skills-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .skills-panel-header {
            background: linear-gradient(180deg, #f8fafc 0%, #f0f6ff 100%);
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
            padding: 0.9rem 1.25rem;
        }

        .skills-panel-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .skills-panel-header .fas {
            color: #2563eb;
            opacity: 0.88;
        }

        .skills-panel-body {
            padding: 0;
        }

        .skills-admin-page .table {
            margin-bottom: 0;
            color: #475569;
        }

        .skills-admin-page .table thead th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.85rem 1.15rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .skills-admin-page .table tbody td {
            padding: 0.85rem 1.15rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .skills-admin-page .table tbody tr:hover {
            background: #f8fafc;
        }

        .skills-admin-page .table tbody tr:last-child td {
            border-bottom: none;
        }

        .skills-badge-cat {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .skills-badge-active {
            font-weight: 600;
            font-size: 0.75rem;
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .skills-badge-inactive {
            font-weight: 600;
            font-size: 0.75rem;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .skills-admin-page .modal-header {
            background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);
            border-bottom: 1px solid #e2e8f0;
        }

        .skills-admin-page .modal-title {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 1rem;
        }

        .skills-admin-page .modal-footer {
            background: #fafbff;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body class="admin-layout skills-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <div class="container-fluid px-0">
            <!-- Header -->
            <div class="skills-page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-0"><i class="fas fa-tools me-2"></i>Skills management</h1>
                    <p class="text-muted mb-0">Manage skills for employee profiles and job postings</p>
                </div>
                <button type="button" class="btn btn-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                    <i class="fas fa-plus me-2"></i>Add new skill
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Skills Table -->
            <div class="skills-panel">
                <div class="skills-panel-header">
                    <h5><i class="fas fa-list me-2"></i>All skills (<?php echo count($skills); ?>)</h5>
                </div>
                <div class="skills-panel-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Skill Name</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skills as $skill): ?>
                                <tr>
                                    <td><?php echo $skill['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong></td>
                                    <td>
                                        <span class="badge skills-badge-cat"><?php echo htmlspecialchars($skill['category']); ?></span>
                                    </td>
                                    <td><span class="text-muted small"><?php echo htmlspecialchars($skill['description'] ?? '—'); ?></span></td>
                                    <td>
                                        <?php if ($skill['status'] == 'active'): ?>
                                            <span class="badge skills-badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge skills-badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo date('M j, Y', strtotime($skill['created_at'])); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSkillModal<?php echo $skill['id']; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this skill?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($skills)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No skills found. Add your first skill!</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Skill Modal -->
    <div class="modal fade" id="addSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Skill</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Skill Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="skill_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" list="categoryList" placeholder="e.g., Technical, Soft Skills, Management">
                            <datalist id="categoryList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Optional description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Skill Modals -->
    <?php foreach ($skills as $skill): ?>
    <div class="modal fade" id="editSkillModal<?php echo $skill['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Skill</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Skill Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="skill_name" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" list="categoryList<?php echo $skill['id']; ?>" value="<?php echo htmlspecialchars($skill['category']); ?>" placeholder="e.g., Technical, Soft Skills, Management">
                            <datalist id="categoryList<?php echo $skill['id']; ?>">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Optional description..."><?php echo htmlspecialchars($skill['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $skill['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $skill['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
