<?php
session_start();
include __DIR__.'/alnaasDB.php';

if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$msg = '';
$toastColor = 'bg-success';
$showToast = false;

//  Send Notification
if (isset($_POST['send'])) {
    $recipient = intval($_POST['recipient_id'] ?: 0);
    $message = trim($_POST['message'] ?? '');
    $status = 'Unread';

    if ($recipient <= 0 || $message === '') {
        $msg = 'Recipient and message required.';
        $toastColor = 'bg-warning';
        $showToast = true;
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (recipient_id, message, status) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $recipient, $message, $status);
        if ($stmt->execute()) {
            $msg = 'Notification sent successfully.';
            $toastColor = 'bg-success';
            $showToast = true;
        } else {
            $msg = 'Error: '.$stmt->error;
            $toastColor = 'bg-danger';
            $showToast = true;
        }
        $stmt->close();
    }
}

// 🗑 Delete Notification (Admin only)
if (isset($_GET['delete']) && $role == 'Admin') {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM notifications WHERE id=$id");
    header("Location: notifications.php");
    exit();
}

// ✏️ Update Notification (Admin only)
if (isset($_POST['update'])) {
    $id = intval($_POST['id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($id > 0 && $message !== '' && in_array($status, ['Read', 'Unread'])) {
        $stmt = $conn->prepare("UPDATE notifications SET message=?, status=? WHERE id=?");
        $stmt->bind_param('ssi', $message, $status, $id);

        if ($stmt->execute()) {
            $msg = 'Notification updated successfully.';
            $toastColor = 'bg-success';
            $showToast = true;
        } else {
            $msg = 'Error updating notification: '.$stmt->error;
            $toastColor = 'bg-danger';
            $showToast = true;
        }
        $stmt->close();
    } else {
        $msg = 'Invalid input.';
        $toastColor = 'bg-warning';
        $showToast = true;
    }
}

//  Fetch Data
$notifications = $conn->query("
    SELECT n.*, u.name AS recipient_name 
    FROM notifications n 
    LEFT JOIN users u ON n.recipient_id = u.ptpID 
    ORDER BY n.created_at DESC
");
$users = $conn->query("SELECT ptpID, name FROM users ORDER BY name");
$unread_notifications = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE status='Unread'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  background:#f4f6fa; font-family:'Poppins', sans-serif; color:#222;
  transition:background 0.4s, color 0.4s;
}
.sidebar {
  height:100vh; width:250px; position:fixed; top:0; left:0;
  background:linear-gradient(180deg,#003087,#0059b3);
  color:white; padding-top:20px; transition:background 0.4s;
}
.sidebar a {
  display:block; padding:12px 25px; color:#ddd; text-decoration:none;
  border-radius:8px; margin:5px 10px; transition:all 0.3s;
}
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff; }

.main-content { margin-left:260px; padding:30px; }
.card {
  border:none; border-radius:15px;
  box-shadow:0 6px 20px rgba(0,0,0,0.1);
  background:white; transition:background 0.4s, color 0.4s;
}
.icon-btn { border:none; background:transparent; font-size:1.2rem; cursor:pointer; }
.icon-edit { color:#0d6efd; } .icon-edit:hover { color:#084298; }
.icon-delete { color:#dc3545; } .icon-delete:hover { color:#a71d2a; }

/* Dark Mode */
body.dark { background:#0e1117; color:#eee; }
.dark .sidebar { background:linear-gradient(180deg,#0d1b2a,#1b263b); }
.dark .sidebar a { color:#aaa; }
.dark .sidebar a:hover, .dark .sidebar a.active { background:rgba(255,255,255,0.1); color:#fff; }
.dark .card { background:#1e1e1e; color:#ddd; box-shadow:0 6px 20px rgba(255,255,255,0.05); }

/* Topbar */
.topbar {
  position:fixed; top:15px; right:25px; display:flex; align-items:center; z-index:1000;
}
.toggle-btn, .notif-btn {
  background:#fff; border:none; border-radius:50px; padding:8px 15px; margin-left:10px;
  box-shadow:0 2px 10px rgba(0,0,0,0.1); cursor:pointer; transition:all 0.3s;
}
.toggle-btn:hover, .notif-btn:hover { background:#e9ecef; }
.notif-btn { position:relative; color:#333; text-decoration:none; }
.notif-btn i { font-size:1.3rem; }
.notif-btn .badge {
  position:absolute; top:-4px; right:-6px; font-size:0.7rem; padding:3px 6px;
}
.dark .toggle-btn, .dark .notif-btn {
  background:#1e1e1e; color:#fff; box-shadow:0 2px 10px rgba(255,255,255,0.05);
}
.dark .toggle-btn:hover, .dark .notif-btn:hover { background:#2a2a2a; }

.table-dark th, .table-dark td { color:#eee !important; }
.toast-container { z-index:2000; }
</style>
</head>
<body>

<!--  Toast -->
<?php if($showToast): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast align-items-center text-white <?= $toastColor ?> border-0 show" role="alert" id="msgToast">
    <div class="d-flex">
      <div class="toast-body"><?= htmlspecialchars($msg) ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- 🔝 Topbar -->
<div class="topbar">
  <a href="notifications.php" class="notif-btn" title="View Notifications">
    <i class="bi bi-bell-fill"></i>
    <?php if ($unread_notifications > 0): ?>
      <span class="badge bg-danger rounded-pill"><?= $unread_notifications ?></span>
    <?php endif; ?>
  </a>
  <button id="themeToggle" class="toggle-btn"><i class="bi bi-moon-fill"></i> Dark Mode</button>
</div>

<!--  Sidebar -->
<div class="sidebar text-center">
  <h3>ALNAAS</h3>
  <a href="alnaasDash.php"><i class="bi bi-house"></i> Dashboard</a>
</div>

<!--  Main Content -->
<div class="main-content">
  <h2 class="fw-bold mb-4">Notifications</h2>

  <!--  Send Notification -->
  <?php if($role == 'Admin'): ?>
  <div class="card p-4 mb-4">
    <h5><i class="bi bi-send"></i> Send Notification</h5>
    <form method="POST">
      <div class="row g-2">
        <div class="col-md-4">
          <select name="recipient_id" class="form-select" required>
            <option value="">-- Select Recipient --</option>
            <?php while($u = $users->fetch_assoc()): ?>
              <option value="<?= (int)$u['ptpID'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-6">
          <input name="message" class="form-control" placeholder="Type your message..." required>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" name="send">Send</button>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!--  All Notifications -->
  <div class="card p-4">
    <h5><i class="bi bi-bell"></i> All Notifications</h5>
    <div class="table-responsive mt-3">
      <table class="table table-hover table-bordered align-middle <?= isset($_COOKIE['theme']) && $_COOKIE['theme']=='dark'?'table-dark':'' ?>">
        <thead class="table-light">
          <tr>
            <th>ID</th><th>Recipient</th><th>Message</th><th>Status</th><th>Created</th>
            <?php if ($role == 'Admin'): ?><th class="text-center">Action</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if($notifications->num_rows > 0): while($n = $notifications->fetch_assoc()): ?>
          <tr class="<?= $n['status']=='Unread'?'table-warning':'' ?>">
            <td><?= $n['id'] ?></td>
            <td><?= htmlspecialchars($n['recipient_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($n['message']) ?></td>
            <td><?= htmlspecialchars($n['status']) ?></td>
            <td><?= htmlspecialchars($n['created_at']) ?></td>
            <?php if ($role == 'Admin'): ?>
            <td class="text-center">
              <button class="icon-btn icon-edit" data-bs-toggle="modal" data-bs-target="#editModal<?= $n['id'] ?>"><i class="bi bi-pencil-square"></i></button>
              <a href="?delete=<?= $n['id'] ?>" class="icon-btn icon-delete" onclick="return confirm('Delete this notification?')"><i class="bi bi-trash"></i></a>
            </td>
            <?php endif; ?>
          </tr>

          <!--  Edit Modal -->
          <div class="modal fade" id="editModal<?= $n['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST" action="">
                  <input type="hidden" name="update" value="1">
                  <input type="hidden" name="id" value="<?= $n['id'] ?>">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label>Message</label>
                      <textarea name="message" class="form-control" required><?= htmlspecialchars($n['message']) ?></textarea>
                    </div>
                    <div class="mb-3">
                      <label>Status</label>
                      <select name="status" class="form-select">
                        <option value="Unread" <?= $n['status']=='Unread'?'selected':'' ?>>Unread</option>
                        <option value="Read" <?= $n['status']=='Read'?'selected':'' ?>>Read</option>
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
            <tr><td colspan="6" class="text-center">No notifications found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
//  Dark Mode Toggle
const toggleBtn = document.getElementById('themeToggle');
const body = document.body;
if (localStorage.getItem('theme') === 'dark') {
  body.classList.add('dark');
  toggleBtn.innerHTML = '<i class="bi bi-brightness-high-fill"></i> Light Mode';
}
toggleBtn.addEventListener('click', () => {
  body.classList.toggle('dark');
  const isDark = body.classList.contains('dark');
  toggleBtn.innerHTML = isDark ? '<i class="bi bi-brightness-high-fill"></i> Light Mode' : '<i class="bi bi-moon-fill"></i> Dark Mode';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
});

// Toast Auto Show
document.addEventListener('DOMContentLoaded', () => {
  const toastEl = document.getElementById('msgToast');
  if (toastEl) {
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
  }
});
</script>
</body>
</html>
