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
$role_options = ['Admin','Staff','Donor'];

// Register user (Admin only)
if (isset($_POST['register_user']) && $_SESSION['role'] == 'Admin') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', $role_options) ? $_POST['role'] : 'Staff';

    if ($username === '' || $password === '') {
        $msg = 'Username and password are required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $username, $email, $hash, $role);
        if ($stmt->execute()) {
            $msg = 'User created successfully.';
        } else {
            $msg = 'Error: '.$stmt->error;
        }
        $stmt->close();
    }
}

// Delete user (Admin only)
if (isset($_GET['delete']) && $_SESSION['role'] == 'Admin') {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE ptpID=$id");
    header("Location: users.php");
    exit();
}

// Fetch users
$users = $conn->query("SELECT ptpID, name, email, role, created_at FROM users ORDER BY created_at ASC");

// NOW include header
$pageTitle = "Users - ALNAAS";
include 'header.php';
?>

<style>
.main-content { padding: 20px; margin-top: 70px; }

.page-header { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.icon-btn {
    background: none;
    border: none;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s;
    border-radius: 6px;
}

.icon-btn:hover {
    background: #f0f0f0;
}

.icon-btn i {
    color: #667eea;
}

.icon-delete i {
    color: #dc3545;
}

.icon-delete:hover {
    background: #fee;
}

.table thead th {
    background: #667eea;
    color: white;
    border: none;
    font-weight: 500;
}
</style>

<div class="main-content">
    <div class="page-header">
        <h2><i class="bi bi-people-fill"></i> Users Management</h2>
        <p style="margin: 8px 0 0 0; opacity: 0.95; font-size: 14px;">Manage system users and permissions</p>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add User Form -->
    <?php if ($_SESSION['role'] == 'Admin'): ?>
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-person-plus"></i> Add New User</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input name="username" class="form-control" placeholder="Enter username" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input name="email" type="email" class="form-control" placeholder="Enter email">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input name="password" type="password" class="form-control" placeholder="Enter password" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <?php foreach($role_options as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" name="register_user">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-list-ul"></i> All Users</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 8%;">ID</th>
                        <th style="width: 22%;">Name</th>
                        <th style="width: 25%;">Email</th>
                        <th style="width: 15%;">Role</th>
                        <th style="width: 20%;">Created</th>
                        <?php if ($_SESSION['role'] == 'Admin'): ?>
                            <th style="width: 10%;" class="text-center">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($users->num_rows > 0): 
                        while($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= $u['ptpID'] ?></strong></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge bg-primary"><?= htmlspecialchars($u['role']) ?></span>
                        </td>
                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <?php if ($_SESSION['role'] == 'Admin'): ?>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['ptpID'] ?>" title="Edit User">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <a href="?delete=<?= $u['ptpID'] ?>" class="icon-btn icon-delete" onclick="return confirm('Delete this user?')" title="Delete User">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>

                    <!-- Edit Modal -->
                    <?php if ($_SESSION['role'] == 'Admin'): ?>
                    <div class="modal fade" id="editModal<?= $u['ptpID'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="update_user.php">
                                    <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                        <h5 class="modal-title">
                                            <i class="bi bi-pencil-square"></i> Edit User #<?= $u['ptpID'] ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $u['ptpID'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" name="name" value="<?= htmlspecialchars($u['name']) ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select name="role" class="form-select" required>
                                                <?php foreach($role_options as $r): ?>
                                                    <option value="<?= $r ?>" <?= $u['role']==$r?'selected':'' ?>><?= $r ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php 
                        endwhile;
                    else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mt-2">No users found.</p>
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