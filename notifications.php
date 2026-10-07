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

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$msg = '';
$toastColor = 'bg-success';
$showToast = false;

// Send Notification
if (isset($_POST['send'])) {
    $recipient = intval($_POST['recipient_id'] ?: 0);
    $message = trim($_POST['message'] ?? '');
    $status = 'unread';

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

// Delete Notification (Admin only)
if (isset($_GET['delete']) && $role == 'Admin') {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM notifications WHERE id=$id");
    header("Location: notifications.php");
    exit();
}

// Update Notification (Admin only)
if (isset($_POST['update'])) {
    $id = intval($_POST['id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($id > 0 && $message !== '' && in_array($status, ['read', 'unread'])) {
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

// Fetch Data
$notifications = $conn->query("
    SELECT n.*, u.name AS recipient_name 
    FROM notifications n 
    LEFT JOIN users u ON n.recipient_id = u.ptpID 
    ORDER BY n.created_at DESC
");
$users = $conn->query("SELECT ptpID, name FROM users ORDER BY name");

// NOW include header - all logic is done
$pageTitle = "Notifications - ALNAAS";
include 'header.php';
?>

<style>
body { background: #f4f6fa; }
.main-content { padding: 20px; margin-top: 70px; }

.page-header { 
    background: linear-gradient(135deg, #3535c6ff 0%, #395bcbff 100%);
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
    background: #3746ebff;
    color: white;
    border: none;
    font-weight: 500;
}

.toast-container { 
    z-index: 2000; 
    position: fixed;
    top: 80px;
    right: 20px;
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

body.dark .icon-btn:hover {
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

body.dark .table-warning {
    background-color: #4a4a2a !important;
    color: #ddd;
}
</style>

<!-- Toast Notification -->
<?php if($showToast): ?>
<div class="toast-container">
    <div class="toast align-items-center text-white <?= $toastColor ?> border-0 show" role="alert" id="msgToast">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars($msg) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="bi bi-bell-fill"></i> Notifications Management</h2>
        <p style="margin: 8px 0 0 0; opacity: 0.95; font-size: 14px;">Send and manage system notifications</p>
    </div>

    <!-- Send Notification -->
    <?php if($role == 'Admin'): ?>
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-send"></i> Send Notification</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Recipient</label>
                    <select name="recipient_id" class="form-select" required>
                        <option value="">-- Select Recipient --</option>
                        <?php 
                        $users_temp = $conn->query("SELECT ptpID, name FROM users ORDER BY name");
                        while($u = $users_temp->fetch_assoc()): 
                        ?>
                            <option value="<?= (int)$u['ptpID'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Message</label>
                    <input name="message" class="form-control" placeholder="Type your message..." required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" name="send">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- All Notifications -->
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-list-ul"></i> All Notifications</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 20%;">Recipient</th>
                        <th style="width: 40%;">Message</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 15%;">Created</th>
                        <?php if ($role == 'Admin'): ?>
                            <th style="width: 10%;" class="text-center">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($notifications->num_rows > 0): 
                        while($n = $notifications->fetch_assoc()): 
                            $isUnread = strtolower($n['status']) === 'unread';
                    ?>
                    <tr class="<?= $isUnread ? 'table-warning' : '' ?>">
                        <td><strong><?= $n['id'] ?></strong></td>
                        <td><?= htmlspecialchars($n['recipient_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($n['message']) ?></td>
                        <td>
                            <span class="badge <?= $isUnread ? 'bg-warning text-dark' : 'bg-success' ?>">
                                <?= htmlspecialchars(ucfirst($n['status'])) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></td>
                        <?php if ($role == 'Admin'): ?>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#editModal<?= $n['id'] ?>" title="Edit Notification">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <a href="?delete=<?= $n['id'] ?>" class="icon-btn icon-delete" onclick="return confirm('Delete this notification?')" title="Delete Notification">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>

                    <!-- Edit Modal -->
                    <?php if ($role == 'Admin'): ?>
                    <div class="modal fade" id="editModal<?= $n['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST">
                                    <input type="hidden" name="update" value="1">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <div class="modal-header" style="background: linear-gradient(135deg, #280bb9ff 0%, #3125baff 100%); color: white;">
                                        <h5 class="modal-title">
                                            <i class="bi bi-pencil-square"></i> Edit Notification #<?= $n['id'] ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Recipient</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($n['recipient_name'] ?? 'Unknown') ?>" disabled>
                                            <small class="text-muted">Recipient cannot be changed</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Message</label>
                                            <textarea name="message" class="form-control" rows="3" required><?= htmlspecialchars($n['message']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select" required>
                                                <option value="unread" <?= strtolower($n['status'])=='unread'?'selected':'' ?>>Unread</option>
                                                <option value="read" <?= strtolower($n['status'])=='read'?'selected':'' ?>>Read</option>
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
                                <p class="mt-2">No notifications found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toast Auto Show and Hide
document.addEventListener('DOMContentLoaded', () => {
    const toastEl = document.getElementById('msgToast');
    if (toastEl) {
        const toast = new bootstrap.Toast(toastEl, { 
            delay: 3000,
            autohide: true 
        });
        toast.show();
    }
});
</script>
</body>
</html>