<?php
session_start();
include 'alnaasDB.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

// Restrict Donors from modifying beneficiaries
$role = $_SESSION['role'] ?? 'Donor';
$canEdit = ($role !== 'Donor');

// ADD Beneficiary
if ($canEdit && isset($_POST['add_beneficiary'])) {
    $stmt = $conn->prepare("INSERT INTO beneficiaries (first_name, last_name, gender, dob, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['dob'], $_POST['phone']);
    $stmt->execute();
    $stmt->close();
    header("Location: beneficiaries.php");
    exit();
}

// UPDATE Beneficiary
if ($canEdit && isset($_POST['update_beneficiary'])) {
    $stmt = $conn->prepare("UPDATE beneficiaries SET first_name=?, last_name=?, gender=?, dob=?, phone=? WHERE id=?");
    $stmt->bind_param("sssssi", $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['dob'], $_POST['phone'], $_POST['id']);
    $stmt->execute();
    $stmt->close();
    header("Location: beneficiaries.php");
    exit();
}

// DELETE Beneficiary
if ($canEdit && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM beneficiaries WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: beneficiaries.php");
    exit();
}

// FETCH all beneficiaries
$result = $conn->query("SELECT * FROM beneficiaries ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beneficiaries - ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f4f6fa; font-family:'Poppins', sans-serif; color:#222; transition: background 0.4s, color 0.4s; }
.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); background:white; }
.table td, .table th { vertical-align: middle; }
.btn-action { font-size: 0.85rem; padding: 4px 10px; }
</style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-content">
  <h2 class="fw-bold mb-4">Beneficiaries</h2>

  <!-- Add Beneficiary Form (Admin/Staff Only) -->
  <?php if($canEdit): ?>
  <div class="card p-4 mb-4">
    <h5>Add New Beneficiary</h5>
    <form method="POST">
      <div class="row">
        <div class="col-md-3 mb-3"><input type="text" name="first_name" class="form-control" placeholder="First Name" required></div>
        <div class="col-md-3 mb-3"><input type="text" name="last_name" class="form-control" placeholder="Last Name"></div>
        <div class="col-md-2 mb-3">
          <select name="gender" class="form-select" required>
            <option value="">Gender</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>
        <div class="col-md-2 mb-3"><input type="date" name="dob" class="form-control"></div>
        <div class="col-md-2 mb-3"><input type="text" name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-2 mb-3"><button type="submit" name="add_beneficiary" class="btn btn-primary w-100">Add</button></div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Beneficiaries Table -->
  <div class="card p-4">
    <h5>All Beneficiaries</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead class="table-primary">
          <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Gender</th>
            <th>DOB</th>
            <th>Phone</th>
            <th>Registered</th>
            <?php if($canEdit): ?><th class="text-center">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['first_name']) ?> <?= htmlspecialchars($row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['gender']) ?></td>
            <td><?= htmlspecialchars($row['dob']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <?php if($canEdit): ?>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-sm btn-info text-white btn-action" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Delete this beneficiary?')"><i class="bi bi-trash"></i> Delete</a>
              </div>
            </td>
            <?php endif; ?>
          </tr>

          <!-- Edit Modal (Admin/Staff Only) -->
          <?php if($canEdit): ?>
          <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form method="POST">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Beneficiary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="mb-3">
                      <label>First Name</label>
                      <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($row['first_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                      <label>Last Name</label>
                      <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($row['last_name']) ?>">
                    </div>
                    <div class="mb-3">
                      <label>Gender</label>
                      <select name="gender" class="form-select" required>
                        <option <?= $row['gender']=='Male'?'selected':'' ?>>Male</option>
                        <option <?= $row['gender']=='Female'?'selected':'' ?>>Female</option>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label>Date of Birth</label>
                      <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($row['dob']) ?>">
                    </div>
                    <div class="mb-3">
                      <label>Phone</label>
                      <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($row['phone']) ?>">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" name="update_beneficiary" class="btn btn-success">Save Changes</button>
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

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
