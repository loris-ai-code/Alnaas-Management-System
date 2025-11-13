<?php
session_start();
include __DIR__.'/alnaasDB.php';
if (!isset($_SESSION['username'])) { header("Location: alnaasLogin.php"); exit(); }

$msg = '';

// fetch categories & types
$cats = $conn->query("SELECT id, category_name FROM post_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$types = $conn->query("SELECT id, type_name FROM post_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);

// Add category
if (isset($_POST['add_category'])) {
    $c = trim($_POST['category_name'] ?? '');
    if ($c !== '') {
        $stmt = $conn->prepare("INSERT INTO post_categories (category_name, description) VALUES (?, ?)");
        $desc = $_POST['category_desc'] ?? '';
        $stmt->bind_param('ss',$c,$desc);
        $stmt->execute() ? $msg = 'Category added.' : $msg = 'Error: '.$stmt->error;
        $stmt->close();
        $cats = $conn->query("SELECT id, category_name FROM post_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
    }
}

// Add type
if (isset($_POST['add_type'])) {
    $t = trim($_POST['type_name'] ?? '');
    if ($t !== '') {
        $stmt = $conn->prepare("INSERT INTO post_types (type_name, description) VALUES (?, ?)");
        $desc = $_POST['type_desc'] ?? '';
        $stmt->bind_param('ss',$t,$desc);
        $stmt->execute() ? $msg = 'Type added.' : $msg = 'Error: '.$stmt->error;
        $stmt->close();
        $types = $conn->query("SELECT id, type_name FROM post_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    }
}

// Add post
if (isset($_POST['add_post'])) {
    $cat = intval($_POST['category_id'] ?: 0) ?: null;
    $type = intval($_POST['type_id'] ?: 0) ?: null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '' || $content === '') { $msg = 'Title and content required for post.'; }
    else {
        $stmt = $conn->prepare("INSERT INTO posts (category_id, type_id, user_id, title, content) VALUES (?, ?, ?, ?, ?)");
        $user = $_SESSION['ptpID'] ?? null;
        $stmt->bind_param('iiiss', $cat, $type, $user, $title, $content);
        $stmt->execute() ? $msg = 'Post saved.' : $msg = 'Error: '.$stmt->error;
        $stmt->close();
    }
}

// fetch posts
$posts = $conn->query("SELECT p.*, c.category_name, t.type_name, u.name as author FROM posts p LEFT JOIN post_categories c ON p.category_id=c.id LEFT JOIN post_types t ON p.type_id=t.id LEFT JOIN users u ON p.user_id=u.ptpID ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Posts - ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{font-family:Poppins,Arial;background:#f4f6fa}.sidebar{position:fixed;width:240px;height:100vh;background:linear-gradient(180deg,#003087,#0059b3);color:#fff;padding:20px}.main{margin-left:260px;padding:28px}.sidebar a{color:#ddd;display:block;padding:10px;margin:6px 0;border-radius:8px;text-decoration:none}</style>
</head>
<body>
  <div class="sidebar">
    <h3 class="text-center">ALNAAS</h3>
    <a href="alnaasDash.php">🔙Dashboard</a>
    
  </div>

  <div class="main">
    <h2>Posts</h2>
    <?php if($msg): ?><div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="card p-3">
          <h5>Add Category</h5>
          <form method="post">
            <input name="category_name" class="form-control mb-2" placeholder="Category name" required>
            <textarea name="category_desc" class="form-control mb-2" placeholder="Description"></textarea>
            <button class="btn btn-secondary" name="add_category">Add Category</button>
          </form>
        </div>

        <div class="card p-3 mt-3">
          <h5>Add Type</h5>
          <form method="post">
            <input name="type_name" class="form-control mb-2" placeholder="Type name" required>
            <textarea name="type_desc" class="form-control mb-2" placeholder="Description"></textarea>
            <button class="btn btn-secondary" name="add_type">Add Type</button>
          </form>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card p-3">
          <h5>Add Post</h5>
          <form method="post">
            <select name="category_id" class="form-select mb-2"><option value="">-- Category --</option><?php foreach($cats as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option><?php endforeach; ?></select>
            <select name="type_id" class="form-select mb-2"><option value="">-- Type --</option><?php foreach($types as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['type_name']); ?></option><?php endforeach;?></select>
            <input name="title" class="form-control mb-2" placeholder="Title" required>
            <textarea name="content" class="form-control mb-2" placeholder="Content" rows="6" required></textarea>
            <button class="btn btn-primary" name="add_post">Save Post</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card p-3 mt-3">
      <h5>All Posts</h5>
      <div class="table-responsive"><table class="table table-hover table-bordered">
       <thead class="table-primary">
        <tr><th>ID</th><th>Title</th><th>Category</th><th>Type</th><th>Author</th><th>Created</th></tr></thead>
        <tbody>
          <?php if(empty($posts)): ?><tr><td colspan="6">No posts yet.</td></tr><?php else: foreach($posts as $p): ?>
            <tr>
              <td><?php echo (int)$p['id']; ?></td>
              <td><?php echo htmlspecialchars($p['title']); ?></td>
              <td><?php echo htmlspecialchars($p['category_name'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($p['type_name'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($p['author'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($p['created_at']); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table></div>
    </div>

  </div>
</body>
</html>
