<?php
//----------------------------------------------------
// SESSION + DATABASE
//----------------------------------------------------
session_start();
include 'alnaasDB.php';

// Not logged in → redirect
if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$role = $_SESSION['role'] ?? 'Staff';

//----------------------------------------------------
// PROCESS: ADD ENROLLMENT
//----------------------------------------------------
if (isset($_POST['add_enrollment']) && ($role == 'Admin' || $role == 'Staff')) {
    $stmt = $conn->prepare("
        INSERT INTO program_enrollments (program_id, beneficiary_id, enrollment_date, status, remarks) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisss",
        $_POST['program_id'],
        $_POST['beneficiary_id'],
        $_POST['enrollment_date'],
        $_POST['status'],
        $_POST['remarks']
    );
    $stmt->execute();
    $stmt->close();

    header("Location: enrollments.php");
    exit();
}

//----------------------------------------------------
// PROCESS: UPDATE ENROLLMENT
//----------------------------------------------------
if (isset($_POST['update_enrollment']) && ($role == 'Admin' || $role == 'Staff')) {
    $stmt = $conn->prepare("
        UPDATE program_enrollments 
        SET program_id=?, beneficiary_id=?, enrollment_date=?, status=?, remarks=? 
        WHERE id=?
    ");
    $stmt->bind_param("iisssi",
        $_POST['program_id'],
        $_POST['beneficiary_id'],
        $_POST['enrollment_date'],
        $_POST['status'],
        $_POST['remarks'],
        $_POST['id']
    );
    $stmt->execute();
    $stmt->close();

    header("Location: enrollments.php");
    exit();
}

//----------------------------------------------------
// PROCESS: DELETE ENROLLMENT (ADMIN ONLY)
//----------------------------------------------------
if (isset($_GET['delete']) && $role == 'Admin') {
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM program_enrollments WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: enrollments.php");
    exit();
}

//----------------------------------------------------
// AFTER LOGIC → INCLUDE HEADER (NO OUTPUT BEFORE THIS)
//----------------------------------------------------
include 'header.php';

//----------------------------------------------------
// FETCH DROPDOWNS
//----------------------------------------------------
$programs = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");
$beneficiaries = $conn->query("SELECT id, first_name, last_name FROM beneficiaries ORDER BY first_name ASC");

//----------------------------------------------------
// FETCH ENROLLMENTS LIST
//----------------------------------------------------
$query = "
    SELECT e.id, p.name AS program_name, 
           CONCAT(b.first_name,' ',b.last_name) AS beneficiary_name, 
           e.enrollment_date, e.status, e.remarks
    FROM program_enrollments e
    JOIN programs p ON e.program_id = p.id
    JOIN beneficiaries b ON e.beneficiary_id = b.id
    ORDER BY e.enrollment_date DESC
";
$enrollments = $conn->query($query);
?>

<!-- ======================================
     MAIN CONTENT
======================================= -->
<div class="main-content">

    <h2 class="fw-bold mb-4">Program Enrollments</h2>

    <!-- ADD ENROLLMENT FORM -->
    <?php if ($role != 'Donor'): ?>
    <div class="card p-4 mb-4">
        <h5>Add New Enrollment</h5>

        <form method="POST">
            <div class="row g-2">

                <!-- Program Dropdown -->
                <div class="col-md-3">
                    <select name="program_id" class="form-select" required>
                        <option value="">Select Program</option>
                        <?php while($p = $programs->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Beneficiary Dropdown -->
                <div class="col-md-3">
                    <select name="beneficiary_id" class="form-select" required>
                        <option value="">Select Beneficiary</option>
                        <?php while($b = $beneficiaries->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['first_name'].' '.$b['last_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Enrollment Date -->
                <div class="col-md-2">
                    <input type="date" name="enrollment_date" class="form-control" required>
                </div>

                <!-- Status -->
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option>Active</option>
                        <option>Completed</option>
                        <option>Dropped</option>
                    </select>
                </div>

                <!-- Remarks -->
                <div class="col-md-2">
                    <input type="text" name="remarks" class="form-control" placeholder="Remarks">
                </div>

                <!-- Submit -->
                <div class="col-md-1">
                    <button type="submit" name="add_enrollment" class="btn btn-primary w-100">
                        Add
                    </button>
                </div>

            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ENROLLMENTS TABLE -->
    <div class="card p-4">
        <h5>All Enrollments</h5>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Program</th>
                        <th>Beneficiary</th>
                        <th>Enrollment Date</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <?php if ($role != 'Donor'): ?>
                            <th class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $enrollments->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['program_name'] ?></td>
                        <td><?= $row['beneficiary_name'] ?></td>
                        <td><?= $row['enrollment_date'] ?></td>
                        <td><?= $row['status'] ?></td>
                        <td><?= $row['remarks'] ?></td>

                        <?php if ($role != 'Donor'): ?>
                        <td class="text-center">

                            <!-- Edit Button -->
                            <button class="btn btn-sm btn-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#editModal<?= $row['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Delete (Admin Only) -->
                            <?php if ($role == 'Admin'): ?>
                            <a href="?delete=<?= $row['id'] ?>"
                               onclick="return confirm('Delete this enrollment?')"
                               class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>

                        </td>
                        <?php endif; ?>
                    </tr>

                    <!-- EDIT MODAL -->
                    <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">

                                <form method="POST">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Edit Enrollment</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">

                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                        <div class="mb-3">
                                            <label>Program</label>
                                            <input type="text" class="form-control" value="<?= $row['program_name'] ?>" disabled>
                                        </div>

                                        <div class="mb-3">
                                            <label>Beneficiary</label>
                                            <input type="text" class="form-control" value="<?= $row['beneficiary_name'] ?>" disabled>
                                        </div>

                                        <div class="mb-3">
                                            <label>Date</label>
                                            <input type="date" name="enrollment_date" class="form-control" value="<?= $row['enrollment_date'] ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label>Status</label>
                                            <select name="status" class="form-select">
                                                <option <?= $row['status']=='Active'?'selected':'' ?>>Active</option>
                                                <option <?= $row['status']=='Completed'?'selected':'' ?>>Completed</option>
                                                <option <?= $row['status']=='Dropped'?'selected':'' ?>>Dropped</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label>Remarks</label>
                                            <input type="text" name="remarks" class="form-control" value="<?= $row['remarks'] ?>">
                                        </div>

                                    </div>

                                    <div class="modal-footer">
                                        <button type="submit" name="update_enrollment" class="btn btn-success">Save Changes</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>

                    <?php endwhile; ?>
                </tbody>

            </table>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
