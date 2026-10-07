<?php
// HANDLE ALL REDIRECTS BEFORE INCLUDING HEADER
include 'alnaasDB.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication before any output
if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$role = $_SESSION['role'] ?? 'Staff';
$canEdit = in_array($role, ['Admin', 'Staff']);

// --- Helper Function for NULL Binding ---
function getEndDateOrNull($date) {
    return (!empty($date) && $date !== '0000-00-00') ? $date : null;
}

// --- CRUD Operations (all redirects happen before header.php is included) ---

// ADD Program
if (isset($_POST['add_program']) && $canEdit) {
    $validStatuses = ['Pending', 'Ongoing', 'Completed'];
    $status = !empty($_POST['status']) && in_array($_POST['status'], $validStatuses) ? $_POST['status'] : 'Pending';
    $endDate = getEndDateOrNull($_POST['end_date']);

    $sql = "INSERT INTO programs (name, description, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $_POST['name'], $_POST['description'], $_POST['start_date'], $endDate, $status);
    $stmt->execute();
    $stmt->close();
    
    header("Location: programs.php");
    exit();
}

// UPDATE Program
if (isset($_POST['update_program']) && $canEdit) {
    $validStatuses = ['Pending', 'Ongoing', 'Completed'];
    $status = !empty($_POST['status']) && in_array($_POST['status'], $validStatuses) ? $_POST['status'] : 'Pending';
    $endDate = getEndDateOrNull($_POST['end_date']);
    
    $sql = "UPDATE programs SET name=?, description=?, start_date=?, end_date=?, status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $_POST['name'], $_POST['description'], $_POST['start_date'], $endDate, $status, $_POST['id']);
    $stmt->execute();
    $stmt->close();
    
    header("Location: programs.php");
    exit();
}

// DELETE Program (Admin only)
if (isset($_GET['delete']) && $role == 'Admin') {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM programs WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: programs.php");
    exit();
}

// Fetch all programs for display
$result = $conn->query("SELECT * FROM programs ORDER BY created_at DESC");

// NOW include header.php - all redirects are done
$pageTitle = "Programs - ALNAAS";
include 'header.php';
?>

<style>
    body { background: #f4f6fa; }
    .main-content { padding: 20px; margin-top: 70px; }
    
    .page-header { 
        background: linear-gradient(135deg, #667eea 0%, #0b59a3ff 100%);
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
        background: #457aebff;
        color: white;
        border: none;
        font-weight: 500;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-pending {
        background: #fff3cd;
        color: #856404;
    }
    
    .status-ongoing {
        background: #cfe2ff;
        color: #084298;
    }
    
    .status-completed {
        background: #d1e7dd;
        color: #0f5132;
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h2><i></i> Programs Management</h2>
        <p style="margin: 8px 0 0 0; opacity: 0.95; font-size: 14px;">Create and manage program activities</p>
    </div>

    <?php if ($canEdit): ?>
    <div class="card">
        <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Add New Program</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Program Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter program name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Pending">Pending</option>
                        <option value="Ongoing" selected>Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter program description..."></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="add_program" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Program
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h5 class="mb-3"><i class="bi bi-list-ul"></i> All Programs</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 20%;">Name</th>
                        <th style="width: 30%;">Description</th>
                        <th style="width: 10%;">Start Date</th>
                        <th style="width: 10%;">End Date</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 15%;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                            $status = $row['status'] ?? 'Ongoing';
                            $statusClass = 'status-pending';
                            if ($status == 'Ongoing') $statusClass = 'status-ongoing';
                            if ($status == 'Completed') $statusClass = 'status-completed';
                    ?>
                    <tr>
                        <td><strong><?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars($row['name']) ?></td> 
                        <td>
                            <small><?= substr($row['description'] ?? '', 0, 80) . (strlen($row['description'] ?? '') > 80 ? '...' : '') ?></small>
                        </td>
                        <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                        <td><?= $row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : '<span class="text-muted">N/A</span>' ?></td>
                        <td>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <?php if ($canEdit): ?>
                                <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>" title="Edit Program">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php endif; ?>
                                <?php if($role=='Admin'): ?>
                                <a href="?delete=<?= $row['id'] ?>" class="icon-btn icon-delete" onclick="return confirm('Delete this program?')" title="Delete Program">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php if ($canEdit): ?>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header" style="background: linear-gradient(135deg, #3756dfff 0%, #2f50c8ff 100%); color: white;">
                                        <h5 class="modal-title">
                                            <i class="bi bi-pencil-square"></i> Edit Program #<?= $row['id'] ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label">Program Name</label>
                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" name="start_date" class="form-control" value="<?= $row['start_date'] ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">End Date</label>
                                                <input type="date" name="end_date" class="form-control" value="<?= $row['end_date'] ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="Pending" <?= ($row['status'] ?? 'Ongoing') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Ongoing" <?= ($row['status'] ?? 'Ongoing') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                                    <option value="Completed" <?= ($row['status'] ?? 'Ongoing') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Description</label>
                                                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="update_program" class="btn btn-primary">
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
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                            <p class="mt-2">No programs found. Add your first program above!</p>
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