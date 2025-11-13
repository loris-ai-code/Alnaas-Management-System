<?php
use Dompdf\Dompdf;

session_start();
include 'alnaasDB.php';
include 'header.php';


if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$role = $_SESSION['role'] ?? 'Staff';

// Upload directory
$uploadDir = "uploads/reports/";
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

// ADD REPORT
if (isset($_POST['add_report']) && ($role == 'Admin' || $role == 'Staff')) {
    $fileName = "";
    if (!empty($_FILES['attachment']['name'])) {
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (in_array($_FILES['attachment']['type'], $allowedTypes)) {
            $fileName = time() . "_" . basename($_FILES['attachment']['name']);
            move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName);
        }
    }
    $stmt = $conn->prepare("INSERT INTO reports (program_id, user_id, title, content, report_date, attachment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $_POST['program_id'], $_SESSION['ptpID'], $_POST['title'], $_POST['content'], $_POST['report_date'], $fileName);
    $stmt->execute();
    $stmt->close();
    header("Location: reports.php");
    exit();
}

// UPDATE REPORT
if (isset($_POST['update_report']) && ($role == 'Admin' || $role == 'Staff')) {
    $fileName = $_POST['existing_attachment'];
    if (!empty($_FILES['attachment']['name'])) {
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (in_array($_FILES['attachment']['type'], $allowedTypes)) {
            $fileName = time() . "_" . basename($_FILES['attachment']['name']);
            move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName);
        }
    }
    $stmt = $conn->prepare("UPDATE reports SET program_id=?, title=?, content=?, report_date=?, attachment=? WHERE id=?");
    $stmt->bind_param("issssi", $_POST['program_id'], $_POST['title'], $_POST['content'], $_POST['report_date'], $fileName, $_POST['id']);
    $stmt->execute();
    $stmt->close();
    header("Location: reports.php");
    exit();
}

// DELETE REPORT
if (isset($_GET['delete']) && $role == 'Admin') {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM reports WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: reports.php");
    exit();
}

// EXPORT PDF/EXCEL
if (isset($_GET['export']) && in_array($role, ['Admin', 'Staff'])) {
    $type = $_GET['export'];
    $query = $conn->query("SELECT r.id, p.name AS program, u.name AS user, r.title, r.content, r.report_date
                           FROM reports r
                           LEFT JOIN programs p ON r.program_id = p.id
                           LEFT JOIN users u ON r.user_id = u.ptpID
                           ORDER BY r.report_date ASC");
    $data = [];
    while ($r = $query->fetch_assoc()) $data[] = $r;

    if ($type == 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=ALNAAS_Reports_" . date('Ymd_His') . ".xls");
        echo "ID\tProgram\tUser\tTitle\tDate\tContent\n";
        foreach ($data as $r) {
            echo "{$r['id']}\t{$r['program']}\t{$r['user']}\t{$r['title']}\t{$r['report_date']}\t" . str_replace("\n", " ", $r['content']) . "\n";
        }
        exit();
    }

    if ($type == 'pdf') {
        require_once __DIR__ . '/vendor/autoload.php';
        $dompdf = new Dompdf();
        $today = date("F j, Y, g:i a");
        $html = '
        <h2 style="text-align:center;color:#003087;">ALNAAS Reports Summary</h2>
        <p style="text-align:center;font-size:12px;">Generated on ' . $today . '</p>
        <table width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:12px;font-family:Arial;">
            <thead>
                <tr style="background:#003087;color:#fff;">
                    <th style="border:1px solid #ccc;">ID</th>
                    <th style="border:1px solid #ccc;">Program</th>
                    <th style="border:1px solid #ccc;">User</th>
                    <th style="border:1px solid #ccc;">Title</th>
                    <th style="border:1px solid #ccc;">Date</th>
                    <th style="border:1px solid #ccc;">Content</th>
                </tr>
            </thead><tbody>';
        foreach ($data as $r) {
            $html .= '<tr>
                <td style="border:1px solid #ccc;">' . $r['id'] . '</td>
                <td style="border:1px solid #ccc;">' . htmlspecialchars($r['program']) . '</td>
                <td style="border:1px solid #ccc;">' . htmlspecialchars($r['user']) . '</td>
                <td style="border:1px solid #ccc;">' . htmlspecialchars($r['title']) . '</td>
                <td style="border:1px solid #ccc;">' . $r['report_date'] . '</td>
                <td style="border:1px solid #ccc;">' . htmlspecialchars(substr($r['content'], 0, 100)) . '...</td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("ALNAAS_Reports_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
        exit();
    }
}

// FETCH DATA
$programs = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");
$reports = $conn->query("SELECT r.*, p.name AS program_name, u.name AS user_name
                         FROM reports r
                         LEFT JOIN programs p ON r.program_id = p.id
                         LEFT JOIN users u ON r.user_id = u.ptpID
                         ORDER BY r.report_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f4f6fa; font-family:'Poppins', sans-serif; }
.sidebar { height:100vh; width:250px; position:fixed; top:0; left:0; background: linear-gradient(180deg, #003087, #0059b3); color:white; padding-top:20px;}
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; border-radius:8px; margin:5px 10px;}
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff;}
.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); background:white; margin-bottom:20px; }
.table td, .table th { vertical-align: middle; }
</style>
</head>
<body>

<div class="sidebar">
  <h3 class="text-center">ALNAAS</h3>
  <a href="alnaasDash.php">🔙 Dashboard</a>
  <a href="reports.php" class="active">Reports</a>
</div>

<div class="main-content">

  <h2 class="fw-bold mb-4">Reports</h2>

  <?php if ($role != 'Donor'): ?>
  <div class="card p-4">
    <h5>Add New Report</h5>
    <form method="POST" enctype="multipart/form-data">
      <div class="row g-2">
        <div class="col-md-3">
          <select name="program_id" class="form-select" required>
            <option value="">Select Program</option>
            <?php while($p = $programs->fetch_assoc()): ?>
              <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3"><input type="text" name="title" class="form-control" placeholder="Report Title" required></div>
        <div class="col-md-2"><input type="date" name="report_date" class="form-control" required></div>
        <div class="col-md-4"><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="col-md-12 mt-2">
          <textarea name="content" class="form-control" rows="3" placeholder="Enter report content..."></textarea>
        </div>
        <div class="col-md-12 mt-3 text-end">
          <button type="submit" name="add_report" class="btn btn-primary px-4">Add Report</button>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($role == 'Admin' || $role == 'Staff'): ?>
  <div class="text-end mb-3 d-flex justify-content-end gap-2 flex-wrap">
    <a href="?export=pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
    <a href="?export=excel" class="btn btn-warning text-dark"><i class="bi bi-file-earmark-spreadsheet"></i> Export Excel</a>
  </div>
  <?php endif; ?>

  <div class="card p-4">
    <h5>All Reports</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead class="table-primary">
          <tr>
            <th>ID</th><th>Program</th><th>User</th><th>Title</th><th>Date</th><th>Attachment</th><th>Content</th><th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $reports->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['program_name'] ?></td>
            <td><?= $row['user_name'] ?></td>
            <td><?= $row['title'] ?></td>
            <td><?= $row['report_date'] ?></td>
            <td>
              <?php if($row['attachment']): ?>
                <a href="<?= $uploadDir.$row['attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
              <?php else: ?>
                <span class="text-muted">No file</span>
              <?php endif; ?>
            </td>
            <td><?= substr($row['content'],0,60) ?>...</td>
            <td class="text-center">
              <?php if($role!='Donor'): ?>
              <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                <?php if($role=='Admin'): ?>
                  <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this report?')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</a>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title">Edit Report #<?= $row['id'] ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                  <div class="modal-body">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="existing_attachment" value="<?= $row['attachment'] ?>">
                    <div class="mb-3">
                      <label>Program</label>
                      <select name="program_id" class="form-select" required>
                        <?php
                        $pgList = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");
                        while($pg = $pgList->fetch_assoc()):
                        ?>
                        <option value="<?= $pg['id'] ?>" <?= ($pg['id']==$row['program_id'])?'selected':'' ?>><?= $pg['name'] ?></option>
                        <?php endwhile; ?>
                      </select>
                    </div>
                    <div class="row g-2">
                      <div class="col-md-6"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" class="form-control" required></div>
                      <div class="col-md-3"><label>Date</label><input type="date" name="report_date" value="<?= $row['report_date'] ?>" class="form-control" required></div>
                      <div class="col-md-3"><label>Attachment</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><small class="text-muted"><?= $row['attachment'] ?></small></div>
                    </div>
                    <div class="mt-3"><label>Content</label><textarea name="content" class="form-control" rows="4"><?= htmlspecialchars($row['content']) ?></textarea></div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_report" class="btn btn-primary">Save Changes</button>
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

include 'footer.php';

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>
