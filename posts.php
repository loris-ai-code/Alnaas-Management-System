<?php
// ALL LOGIC AND REDIRECTS BEFORE INCLUDING HEADER
include __DIR__.'/alnaasDB.php';

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authentication check - redirect BEFORE including header.php
if (!isset($_SESSION['username'])) { 
    header("Location: alnaasLogin.php"); 
    exit(); 
}

$msg = '';

// Fetch categories & types
$cats = $conn->query("SELECT id, category_name FROM post_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$types = $conn->query("SELECT id, type_name FROM post_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);

// Add category
if (isset($_POST['add_category'])) {
    $c = trim($_POST['category_name'] ?? '');
    if ($c !== '') {
        $stmt = $conn->prepare("INSERT INTO post_categories (category_name, description) VALUES (?, ?)");
        $desc = $_POST['category_desc'] ?? '';
        $stmt->bind_param('ss', $c, $desc);
        if ($stmt->execute()) {
            $msg = 'Category added successfully.';
        } else {
            $msg = 'Error: '.$stmt->error;
        }
        $stmt->close();
        // Refresh categories
        $cats = $conn->query("SELECT id, category_name FROM post_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
    }
}

// Add type
if (isset($_POST['add_type'])) {
    $t = trim($_POST['type_name'] ?? '');
    if ($t !== '') {
        $stmt = $conn->prepare("INSERT INTO post_types (type_name, description) VALUES (?, ?)");
        $desc = $_POST['type_desc'] ?? '';
        $stmt->bind_param('ss', $t, $desc);
        if ($stmt->execute()) {
            $msg = 'Type added successfully.';
        } else {
            $msg = 'Error: '.$stmt->error;
        }
        $stmt->close();
        // Refresh types
        $types = $conn->query("SELECT id, type_name FROM post_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    }
}

// Add post
if (isset($_POST['add_post'])) {
    $cat = intval($_POST['category_id'] ?: 0) ?: null;
    $type = intval($_POST['type_id'] ?: 0) ?: null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if ($title === '' || $content === '') { 
        $msg = 'Title and content required for post.'; 
    } else {
        $stmt = $conn->prepare("INSERT INTO posts (category_id, type_id, user_id, title, content) VALUES (?, ?, ?, ?, ?)");
        $user = $_SESSION['ptpID'] ?? null;
        $stmt->bind_param('iiiss', $cat, $type, $user, $title, $content);
        if ($stmt->execute()) {
            $msg = 'Post saved successfully.';
        } else {
            $msg = 'Error: '.$stmt->error;
        }
        $stmt->close();
    }
}

// Fetch posts
$posts = $conn->query("
    SELECT p.*, 
           c.category_name, 
           t.type_name, 
           u.name as author 
    FROM posts p 
    LEFT JOIN post_categories c ON p.category_id=c.id 
    LEFT JOIN post_types t ON p.type_id=t.id 
    LEFT JOIN users u ON p.user_id=u.ptpID 
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// NOW include header - all logic is done
$pageTitle = "Posts - ALNAAS";
include 'header.php';
?>

<style>
body { background: #f4f6fa; }
.main-content { padding: 20px; margin-top: 70px; }

.page-header { 
    background: linear-gradient(135deg, #3f41e3ff 0%, #3d3aefff 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.page-header h2 { margin: 0; font-weight: 600; }

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 20px;
    padding: 25px;
}

.table thead th {
    background: #4635ffff;
    color: white;
    border: none;
    font-weight: 500;
}

/* Dark mode adjustments */
body.dark .card {
    background: #1e1e1e;
    color: #ddd;
}

body.dark .table {
    color: #ddd;
}

body.dark .table thead th {
    background: #2a2a2a;
}

body.dark .form-control,
body.dark .form-select {
    background: #2a2a2a;
    color: #ddd;
    border-color: #444;
}

body.dark .modal-content {
    background: #1e1e1e;
    color: #ddd;
}
</style>

<div class="main-content">
    <div class="page-header">
        <h2><i class="bi bi-file-text-fill"></i> Posts Management</h2>
        <p style="margin: 8px 0 0 0; opacity: 0.95; font-size: 14px;">Create and organize posts with categories and types</p>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <!-- Add Category Card -->
        <div class="col-md-6">
            <div class="card">
                <h5 class="mb-3"><i class="bi bi-folder-plus"></i> Add Category</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input name="category_name" class="form-control" placeholder="Enter category name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="category_desc" class="form-control" rows="2" placeholder="Enter description (optional)"></textarea>
                    </div>
                    <button class="btn btn-secondary" name="add_category">
                        <i class="bi bi-plus-lg"></i> Add Category
                    </button>
                </form>
            </div>
        </div>

        <!-- Add Type Card -->
        <div class="col-md-6">
            <div class="card">
                <h5 class="mb-3"><i class="bi bi-tag-fill"></i> Add Type</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Type Name</label>
                        <input name="type_name" class="form-control" placeholder="Enter type name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="type_desc" class="form-control" rows="2" placeholder="Enter description (optional)"></textarea>
                    </div>
                    <button class="btn btn-secondary" name="add_type">
                        <i class="bi bi-plus-lg"></i> Add Type
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Post Card -->
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Add New Post</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- Select Category (Optional) --</option>
                        <?php foreach($cats as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="type_id" class="form-select">
                        <option value="">-- Select Type (Optional) --</option>
                        <?php foreach($types as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Title</label>
                    <input name="title" class="form-control" placeholder="Enter post title" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="Enter post content..." required></textarea>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary" name="add_post">
                        <i class="bi bi-check-lg"></i> Save Post
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- All Posts Table -->
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-list-ul"></i> All Posts</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 25%;">Title</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 15%;">Author</th>
                        <th style="width: 15%;">Created</th>
                        <th style="width: 10%;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($posts)): 
                        foreach($posts as $p): ?>
                    <tr>
                        <td><strong><?php echo (int)$p['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                        <td>
                            <?php if($p['category_name']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($p['category_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['type_name']): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($p['type_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['author'] ?? '—'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $p['id'] ?>" title="View Post">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- View Modal -->
                    <div class="modal fade" id="viewModal<?= $p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #2b41ceff 0%, #3047daff 100%); color: white;">
                                    <h5 class="modal-title">
                                        <i class="bi bi-file-text"></i> <?= htmlspecialchars($p['title']) ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <strong>Category:</strong> <?= htmlspecialchars($p['category_name'] ?? 'None') ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Type:</strong> <?= htmlspecialchars($p['type_name'] ?? 'None') ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Author:</strong> <?= htmlspecialchars($p['author'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Created:</strong> <?= date('F d, Y \a\t H:i', strtotime($p['created_at'])) ?>
                                    </div>
                                    <hr>
                                    <div>
                                        <strong>Content:</strong>
                                        <p class="mt-2"><?= nl2br(htmlspecialchars($p['content'])) ?></p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; 
                    else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mt-2">No posts yet. Create your first post above!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>