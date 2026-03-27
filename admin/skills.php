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
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0"><i class="fas fa-tools me-2"></i>Skills Management</h1>
                    <p class="text-muted">Manage skills for employee profiles and job postings</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                    <i class="fas fa-plus me-2"></i>Add New Skill
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
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Skills (<?php echo count($skills); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                        <span class="badge bg-info"><?php echo htmlspecialchars($skill['category']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($skill['description'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($skill['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($skill['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSkillModal<?php echo $skill['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $skill['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this skill?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
