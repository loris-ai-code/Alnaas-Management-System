<?php
session_start();
include __DIR__.'/alnaasDB.php';

// --- Check Admin ---
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin'){
    header("Location: alnaasLogin.php");
    exit();
}

// --- Handle Status Update ---
if(isset($_GET['mark']) && isset($_GET['id'])){
    $status = ($_GET['mark']==='resolve') ? 'Resolved' : 'Unresolved';
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE feedback SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: feedback.php"); // Prevent refresh duplicates
    exit();
}

// --- Fetch all feedback ---
$feedbacks = $conn->query("SELECT * FROM feedback ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback Management | ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f4f6fa; font-family:'Poppins', sans-serif; color:#222; }
.sidebar { height:100vh; width:250px; position:fixed; top:0; left:0; background:linear-gradient(180deg,#003087,#0059b3); color:white; padding-top:20px; }
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; border-radius:8px; margin:5px 10px; }
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff; }
.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); }
.status-badge { font-size:0.8rem; padding:0.4em 0.6em; }
</style>
</head>
<body>
<?php include __DIR__.'/header.php'; ?>

<div class="main-content">
    <h2 class="mb-3">Feedback Management</h2>
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($feedbacks)): ?>
                        <?php foreach($feedbacks as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['id']) ?></td>
                            <td><?= htmlspecialchars($f['name']) ?></td>
                            <td><?= htmlspecialchars($f['email']) ?></td>
                            <td><?= htmlspecialchars($f['message']) ?></td>
                            <td>
                                <span class="badge <?= $f['status']=='Resolved'?'bg-success':'bg-warning text-dark' ?> status-badge">
                                    <?= htmlspecialchars($f['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y H:i', strtotime($f['created_at'])) ?></td>
                            <td>
                                <?php if($f['status'] !== 'Resolved'): ?>
                                    <a href="?mark=resolve&id=<?= $f['id'] ?>" class="btn btn-sm btn-success">Mark Resolved</a>
                                <?php else: ?>
                                    <a href="?mark=unresolve&id=<?= $f['id'] ?>" class="btn btn-sm btn-warning text-dark">Mark Unresolved</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No feedback found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/footer.php'; ?>
</body>
</html>
