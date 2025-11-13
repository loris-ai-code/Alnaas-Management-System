<?php
session_start();
include __DIR__.'/alnaasDB.php';

// --- Redirect users to their correct dashboard ---
if(!isset($_SESSION['username']) || !isset($_SESSION['role'])){
    header("Location: alnaasLogin.php");
    exit();
}

switch($_SESSION['role']){
    case 'Admin':
        break;
    case 'Staff':
        header("Location: staffDashboard.php");
        exit();
    case 'Donor':
        header("Location: donorDashboard.php");
        exit();
    default:
        session_destroy();
        header("Location: alnaasLogin.php");
        exit();
}

// --- Handle Updates ---
if(isset($_POST['updateDonation'])){
    $id = intval($_POST['donation_id']);
    $amount = floatval($_POST['amount']);
    $category = $_POST['category'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE donations SET amount=?, category=?, status=? WHERE id=?");
    $stmt->bind_param("dssi", $amount, $category, $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: alnaasDash.php");
    exit();
}

// --- Handle Delete ---
if(isset($_POST['deleteDonation'])){
    $id = intval($_POST['donation_id']);
    $stmt = $conn->prepare("DELETE FROM donations WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();
    header("Location: alnaasDash.php");
    exit();
}

// --- Fetch stats ---
$total_beneficiaries = $conn->query("SELECT COUNT(*) AS c FROM beneficiaries")->fetch_assoc()['c'] ?? 0;
$total_programs = $conn->query("SELECT COUNT(*) AS c FROM programs")->fetch_assoc()['c'] ?? 0;
$total_reports = $conn->query("SELECT COUNT(*) AS c FROM reports")->fetch_assoc()['c'] ?? 0;
$total_posts = $conn->query("SELECT COUNT(*) AS c FROM posts")->fetch_assoc()['c'] ?? 0;
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
$total_notifications = $conn->query("SELECT COUNT(*) AS c FROM notifications")->fetch_assoc()['c'] ?? 0;
$total_donations_amount = $conn->query("SELECT SUM(amount) AS total FROM donations WHERE status='Completed'")->fetch_assoc()['total'] ?? 0;
$total_feedback = $conn->query("SELECT COUNT(*) AS c FROM feedback")->fetch_assoc()['c'] ?? 0;

// --- Cards array ---
$cards = [
    ['label'=>'Beneficiaries','count'=>$total_beneficiaries,'link'=>'beneficiaries.php','class'=>'text-bg-primary'],
    ['label'=>'Programs','count'=>$total_programs,'link'=>'programs.php','class'=>'text-bg-success'],
    ['label'=>'Reports','count'=>$total_reports,'link'=>'reports.php','class'=>'text-bg-warning text-dark'],
    ['label'=>'Posts','count'=>$total_posts,'link'=>'posts.php','class'=>'text-bg-info text-dark'],
    ['label'=>'Users','count'=>$total_users,'link'=>'users.php','class'=>'text-bg-danger'],
    ['label'=>'Notifications','count'=>$total_notifications,'link'=>'notifications.php','class'=>'text-bg-secondary'],
    ['label'=>'Donations','count'=>$total_donations_amount,'link'=>'#donationsSection','class'=>'text-bg-dark text-white'],
    ['label'=>'Feedback','count'=>$total_feedback,'link'=>'feedback.php','class'=>'text-bg-light text-dark'],
];

// --- Fetch all donations ---
$donations = $conn->query("
    SELECT d.id, d.amount, d.category, d.status, d.created_at, u.name AS donor_name, p.name AS program_name
    FROM donations d
    LEFT JOIN users u ON d.donor_id=u.ptpID
    LEFT JOIN programs p ON d.program_id=p.id
    ORDER BY d.created_at DESC
")->fetch_all(MYSQLI_ASSOC) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALNAAS Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background:#f4f6fa; font-family:'Poppins', sans-serif; color:#222; transition:background 0.4s, color 0.4s; }
.sidebar { height:100vh; width:250px; position:fixed; top:0; left:0; background:linear-gradient(180deg,#003087,#0059b3); color:white; padding-top:20px; transition:0.4s; }
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; border-radius:8px; margin:5px 10px; }
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff; }
.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); transition:0.3s; }
.dashboard-card { cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:140px; text-align:center; padding:25px; transition:0.3s; }
.dashboard-card:hover { transform:translateY(-6px) scale(1.03); box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.card-link { text-decoration:none; color:inherit; display:block; height:100%; }
.counter { font-size:2rem; font-weight:700; }
.chart-card { cursor:pointer; transition:transform 0.3s ease, box-shadow 0.3s ease; }
.chart-card:hover { transform:translateY(-5px) scale(1.03); box-shadow:0 10px 25px rgba(0,0,0,0.15); }
</style>
</head>
<body>
<?php include __DIR__.'/header.php'; ?>

<div class="main-content">
    <h2 class="fw-bold mb-2">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
    <p class="text-muted mb-4">Role: <span class="badge bg-primary"><?= htmlspecialchars($_SESSION['role']) ?></span></p>

    <!-- Dashboard Cards -->
    <div class="row g-4 mb-4">
        <?php foreach($cards as $card): ?>
        <div class="col-md-3">
            <a href="<?= $card['link'] ?>" class="card-link">
                <div class="card dashboard-card <?= $card['class'] ?>">
                    <h6><?= $card['label'] ?></h6>
                    <div class="counter" data-count="<?= $card['count'] ?>">0</div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Section (middle) -->
    <?php
    $genderData = $conn->query("SELECT gender, COUNT(*) AS c FROM beneficiaries GROUP BY gender")->fetch_all(MYSQLI_ASSOC);
    $postData = $conn->query("SELECT c.category_name, COUNT(p.id) AS total FROM posts p LEFT JOIN post_categories c ON p.category_id=c.id GROUP BY c.category_name")->fetch_all(MYSQLI_ASSOC);
    $reportData = $conn->query("SELECT MONTH(report_date) AS month, COUNT(*) AS total FROM reports WHERE report_date IS NOT NULL GROUP BY MONTH(report_date)")->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-5">
            <div class="card p-3 text-center chart-card">
                <h6>Beneficiaries by Gender</h6>
                <div style="position:relative; height:220px; width:220px; margin:auto;">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-5">
            <div class="card p-3 text-center chart-card">
                <h6>Posts by Category</h6>
                <div style="position:relative; height:220px; width:220px; margin:auto;">
                    <canvas id="postsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-10">
            <div class="card p-3 text-center chart-card">
                <h6>Reports per Month</h6>
                <div style="height:300px;">
                    <canvas id="reportsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- All Donations (last section) -->
    <div class="card p-3 mb-4" id="donationsSection">
        <h4>All Donations</h4>
        <p><strong>Status meaning:</strong> Pending = waiting approval, Approved = confirmed, Rejected = declined</p>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Donor</th>
                        <th>Program</th>
                        <th>Amount</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!empty($donations)): ?>
                    <?php foreach($donations as $d): ?>
                    <tr>
                        <td><?= $d['id'] ?></td>
                        <td><?= htmlspecialchars($d['donor_name']) ?></td>
                        <td><?= htmlspecialchars($d['program_name'] ?? 'N/A') ?></td>
                        <td>$<?= number_format($d['amount'],2) ?></td>
                        <td><?= htmlspecialchars($d['category']) ?></td>
                        <td><?= htmlspecialchars($d['status']) ?></td>
                        <td><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $d['id'] ?>">Edit</button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $d['id'] ?>">Delete</button>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Edit Donation #<?= $d['id'] ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                                <div class="mb-2">
                                    <label>Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="amount" value="<?= $d['amount'] ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label>Category</label>
                                    <select class="form-select" name="category">
                                        <option <?= $d['category']=='Money'?'selected':'' ?>>Money</option>
                                        <option <?= $d['category']=='Clothes'?'selected':'' ?>>Clothes</option>
                                        <option <?= $d['category']=='Other'?'selected':'' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Status</label>
                                    <select class="form-select" name="status">
                                        <option <?= $d['status']=='Pending'?'selected':'' ?>>Pending</option>
                                        <option <?= $d['status']=='Approved'?'selected':'' ?>>Approved</option>
                                        <option <?= $d['status']=='Rejected'?'selected':'' ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="updateDonation" class="btn btn-success">Save</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Delete Modal -->
                    <div class="modal fade" id="deleteModal<?= $d['id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Delete Donation #<?= $d['id'] ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                                <p>Are you sure you want to delete this donation?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="deleteDonation" class="btn btn-danger">Delete</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted">No donations found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.counter').forEach(counter=>{
    let countTo=parseInt(counter.getAttribute('data-count'));
    let count=0;
    let step=Math.ceil(countTo/100);
    let interval=setInterval(()=>{
        count+=step;
        if(count>=countTo){count=countTo; clearInterval(interval);}
        counter.innerText=count;
    },20);
});

const genderLabels = <?= json_encode(array_column($genderData,'gender')) ?>;
const genderCounts = <?= json_encode(array_map('intval', array_column($genderData,'c'))) ?>;
const postLabels = <?= json_encode(array_column($postData,'category_name')) ?>;
const postCounts = <?= json_encode(array_map('intval', array_column($postData,'total'))) ?>;
const reportLabels = <?= json_encode(array_map(fn($r)=>date('M',mktime(0,0,0,$r['month'],10)),$reportData)) ?>;
const reportCounts = <?= json_encode(array_map('intval', array_column($reportData,'total'))) ?>;

new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: { labels: genderLabels, datasets: [{ data: genderCounts, backgroundColor:['#0d6efd','#dc3545','#ffc107'] }] }
});
new Chart(document.getElementById('postsChart'), {
    type: 'doughnut',
    data: { labels: postLabels, datasets: [{ data: postCounts, backgroundColor:['#0dcaf0','#6610f2','#fd7e14','#198754','#dc3545'] }] }
});
new Chart(document.getElementById('reportsChart'), {
    type: 'bar',
    data: { labels: reportLabels, datasets: [{ label:'Reports', data: reportCounts, backgroundColor:'#198754' }] },
    options: { plugins:{ legend:{ display:false } } }
});
</script>
</body>
</html>
