<?php
session_start();
include __DIR__.'/alnaasDB.php';

if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$msg = '';
$role_options = ['Admin','Staff','Donor'];

//  Register user (Admin only)
if (isset($_POST['register_user']) && $_SESSION['role'] == 'Admin') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', $role_options) ? $_POST['role'] : 'Staff';

    if ($username === '' || $password === '') $msg = 'Username and password are required.';
    else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $username, $email, $hash, $role);
        if ($stmt->execute()) $msg = 'User created successfully.';
        else $msg = ' Error: '.$stmt->error;
        $stmt->close();
    }
}

//  Delete user (Admin only)
if (isset($_GET['delete']) && $_SESSION['role'] == 'Admin') {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE ptpID=$id");
    header("Location: users.php");
    exit();
}

// Fetch users
$users = $conn->query("SELECT ptpID, name, email, role, created_at FROM users ORDER BY created_at ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users - ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f4f6fa; font-family:'Poppins', sans-serif; }
.sidebar {
    height: 100vh; width: 250px; position: fixed; top: 0; left: 0;
    background: linear-gradient(180deg, #003087, #0059b3);
    color: white; padding-top: 20px;
    border-radius: 0 15px 15px 0;
}
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; border-radius:8px; margin:5px 10px; transition:all 0.3s; }
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff; }
.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); background:white; }
.icon-btn { border:none; background:transparent; color:#007bff; font-size:1.2rem; cursor:pointer; }
.icon-btn:hover { color:#0056b3; }
.icon-delete { color:#dc3545; }
.icon-delete:hover { color:#a71d2a; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <h3 class="text-center">ALNAAS</h3>
  <a href="alnaasDash.php">🔙Dashboard</a>
 
</div>

<!-- Main Content -->
<div class="main-content">
  <h2 class="fw-bold mb-4">Users</h2>

  <?php if($msg): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <!-- Add User Form -->
  <?php if ($_SESSION['role'] == 'Admin'): ?>
  <div class="card p-4 mb-4">
    <h5>Add New User</h5>
    <form method="POST">
      <div class="row g-2">
        <div class="col-md-3"><input name="username" class="form-control" placeholder="Username" required></div>
        <div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="Email"></div>
        <div class="col-md-3"><input name="password" type="password" class="form-control" placeholder="Password" required></div>
        <div class="col-md-2">
          <select name="role" class="form-select">
            <?php foreach($role_options as $r): ?>
              <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1"><button class="btn btn-primary w-100" name="register_user">Add</button></div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Users Table -->
  <div class="card p-4">
    <h5>All Users</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead class="table-primary">
          <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th>
            <?php if ($_SESSION['role'] == 'Admin'): ?><th class="text-center">Action</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if($users->num_rows > 0): while($u = $users->fetch_assoc()): ?>
          <tr>
            <td><?= $u['ptpID'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
            <?php if ($_SESSION['role'] == 'Admin'): ?>
            <td class="text-center">
              <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['ptpID'] ?>"><i class="bi bi-pencil-square"></i></button>
              <a href="?delete=<?= $u['ptpID'] ?>" class="icon-btn icon-delete" onclick="return confirm('Delete this user?')"><i class="bi bi-trash"></i></a>
            </td>
            <?php endif; ?>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="editModal<?= $u['ptpID'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST" action="update_user.php">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="id" value="<?= $u['ptpID'] ?>">
                    <div class="mb-3"><label>Name</label><input type="text" name="name" value="<?= htmlspecialchars($u['name']) ?>" class="form-control"></div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" class="form-control"></div>
                    <div class="mb-3">
                      <label>Role</label>
                      <select name="role" class="form-select">
                        <?php foreach($role_options as $r): ?>
                          <option value="<?= $r ?>" <?= $u['role']==$r?'selected':'' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
