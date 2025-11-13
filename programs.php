<?php
session_start();
// ASSUMPTION: 'alnaasDB.php' establishes the $conn object (MySQLi connection)
include 'alnaasDB.php'; 

// Define title for header.php
$pageTitle = "Programs - ALNAAS"; 
// ASSUMPTION: 'header.php' contains the start of HTML and navigation
include 'header.php'; 

// --- Authentication and Setup ---
if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$role = $_SESSION['role'] ?? 'Staff';
$canEdit = in_array($role, ['Admin', 'Staff']);

// --- Helper Function for NULL Binding ---

/**
 * Handles setting the end date to NULL for the database if it's empty.
 */
function getEndDateOrNull($date) {
    return (!empty($date) && $date !== '0000-00-00') ? $date : null;
}

// --- CRUD Operations ---

// ADD Program
if (isset($_POST['add_program']) && $canEdit) {
    // Sanitize status input
    $validStatuses = ['Pending', 'Ongoing', 'Completed'];
    $status = !empty($_POST['status']) && in_array($_POST['status'], $validStatuses) ? $_POST['status'] : 'Pending';
    
    // Use helper function to handle NULL date
    $endDate = getEndDateOrNull($_POST['end_date']);

    // SQL query now correctly uses 'name' based on the provided schema
    $sql = "INSERT INTO programs (name, description, start_date, end_date, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters: 'name', 'description', 'start_date' are strings (s). 'end_date' may be NULL.
    // For end_date, we bind the variable, and MySQLi handles the NULL value correctly when using 's'.
    $stmt->bind_param("ssss", $_POST['name'], $_POST['description'], $_POST['start_date'], $endDate);
    
    $stmt->execute();
    $stmt->close();
    header("Location: programs.php");
    exit();
}

// UPDATE Program
if (isset($_POST['update_program']) && $canEdit) {
    // Sanitize status input
    $validStatuses = ['Pending', 'Ongoing', 'Completed'];
    $status = !empty($_POST['status']) && in_array($_POST['status'], $validStatuses) ? $_POST['status'] : 'Pending';
    
    // Use helper function to handle NULL date
    $endDate = getEndDateOrNull($_POST['end_date']);
    
    // SQL query correctly uses 'name'
    $sql = "UPDATE programs SET name=?, description=?, start_date=?, end_date=?, status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    
    // Binding parameters: sssssi (name, desc, start_date, end_date, status, id)
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
?>

<style>
/* Custom styles for action buttons */
.icon-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-size: 1rem;
    color: #007bff;
    transition: color 0.2s;
}
.icon-btn:hover {
    color: #0056b3;
}
.icon-delete {
    color: #dc3545;
}
.icon-delete:hover {
    color: #bd2130;
}
</style>

<div class="main-content">
  <h2 class="fw-bold mb-4">Programs</h2>

  <?php if ($canEdit): ?>
  <div class="card p-4 mb-4">
    <h5>Add New Program</h5>
    <form method="POST">
      <div class="row g-2">
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Program Name" required></div>
        <div class="col-md-2"><input type="date" name="start_date" class="form-control" placeholder="Start Date" required></div>
        <div class="col-md-2"><input type="date" name="end_date" class="form-control" placeholder="End Date"></div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">Status </option>
            <option value="Pending">Pending</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" name="add_program" class="btn btn-primary w-100">Add Program</button>
        </div>
        <div class="col-12 mt-3">
          <textarea name="description" class="form-control" rows="3" placeholder="Program Description..."></textarea>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="card p-4">
    <h5>All Programs</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead class="table-primary">
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td> 
            <td><?= substr($row['description'] ?? '', 0, 60) . (strlen($row['description'] ?? '') > 60 ? '...' : '') ?></td>
            <td><?= $row['start_date'] ?></td>
            <td><?= $row['end_date'] ?? 'N/A' ?></td>
            <td><?= $row['status'] ?? 'N/A' ?></td>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">
                <?php if ($canEdit): ?>
                <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                <?php endif; ?>
                <?php if($role=='Admin'): ?>
                <a href="?delete=<?= $row['id'] ?>" class="icon-btn icon-delete" onclick="return confirm('Delete this program?')"><i class="bi bi-trash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>

          <?php if ($canEdit): ?>
          <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
              <div class="modal-content">
                <form method="POST">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="row g-3">
                      <div class="col-md-6"><label>Program Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                      </div>
                      <div class="col-md-3"><label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $row['start_date'] ?>">
                      </div>
                      <div class="col-md-3"><label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $row['end_date'] ?>">
                      </div>
                      <div class="col-md-4">
                        <label>Status</label>
                        <select name="status" class="form-select">
                          <option value="Pending" <?= ($row['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                          <option value="Ongoing" <?= ($row['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                          <option value="Completed" <?= ($row['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                      </div>
                      <div class="col-md-12 mt-2">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" name="update_program" class="btn btn-success">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php 
// ASSUMPTION: 'footer.php' contains the closing HTML and JavaScript includes
include 'footer.php'; 
?>