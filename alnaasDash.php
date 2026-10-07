<?php
session_start();
include __DIR__ . '/alnaasDB.php';

// --- SESSION & ROLE CHECK ---
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: alnaasLogin.php");
    exit();
}

switch ($_SESSION['role']) {
    case 'Admin': break;
    case 'Staff': header("Location: staffDashboard.php"); exit();
    case 'Donor': header("Location: donorDashboard.php"); exit();
    default: session_destroy(); header("Location: alnaasLogin.php"); exit();
}

// --- HELPER FUNCTIONS ---
function safe_str($v) { return trim((string)$v); }
function flash_and_redirect($msg, $type='success', $to='alnaasDash.php') {
    header("Location: {$to}?msg=".urlencode($msg)."&type=".urlencode($type)); exit();
}

// --- DONATION HANDLERS ---
if (isset($_POST['updateDonation'])) {
    $id = intval($_POST['donation_id']);
    $amount = floatval($_POST['amount']);
    $category = safe_str($_POST['category'] ?? '');
    $status = safe_str($_POST['status'] ?? '');
    if ($id <= 0 || $category === '' || $status === '') flash_and_redirect("Invalid input.","danger");
    $stmt = $conn->prepare("UPDATE donations SET amount=?, category=?, status=? WHERE id=?");
    $stmt->bind_param("dssi",$amount,$category,$status,$id);
    $stmt->execute(); $stmt->close();
    flash_and_redirect("Donation #$id updated.");
}

if (isset($_POST['deleteDonation'])) {
    $id = intval($_POST['donation_id']);
    if ($id <= 0) flash_and_redirect("Invalid donation ID.","danger");
    $stmt = $conn->prepare("DELETE FROM donations WHERE id=?");
    $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    flash_and_redirect("Donation #$id deleted.");
}

if (isset($_POST['quickAction'],$_POST['donation_id'])) {
    $id=intval($_POST['donation_id']);
    $action=safe_str($_POST['quickAction']);
    if($id<=0) flash_and_redirect("Invalid donation ID.","danger");
    $statusMap=['approve'=>'Approved','reject'=>'Rejected','complete'=>'Completed'];
    if(!isset($statusMap[$action])) flash_and_redirect("Unknown action.","danger");
    $newStatus=$statusMap[$action];
    $stmt=$conn->prepare("UPDATE donations SET status=? WHERE id=?");
    $stmt->bind_param("si",$newStatus,$id); $stmt->execute(); $stmt->close();
    flash_and_redirect("Donation #$id marked as $newStatus.");
}

// --- FETCH STATS ---
$total_beneficiaries    = $conn->query("SELECT COUNT(*) AS c FROM beneficiaries")->fetch_assoc()['c'] ?? 0;
$total_programs         = $conn->query("SELECT COUNT(*) AS c FROM programs")->fetch_assoc()['c'] ?? 0;
$total_enrollments      = $conn->query("SELECT COUNT(*) AS c FROM program_enrollments")->fetch_assoc()['c'] ?? 0;
$total_reports          = $conn->query("SELECT COUNT(*) AS c FROM reports")->fetch_assoc()['c'] ?? 0;
$total_posts            = $conn->query("SELECT COUNT(*) AS c FROM posts")->fetch_assoc()['c'] ?? 0;
$total_users            = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
$total_donations_amount = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM donations WHERE status='Completed'")->fetch_assoc()['total'] ?? 0;
$total_feedback         = $conn->query("SELECT COUNT(*) AS c FROM feedback")->fetch_assoc()['c'] ?? 0;

// --- DASHBOARD CARDS ---
$cards = [
    ['label'=>'Beneficiaries','count'=>$total_beneficiaries,'link'=>'beneficiaries.php','class'=>'card-primary'],
    ['label'=>'Programs','count'=>$total_programs,'link'=>'programs.php','class'=>'card-success'],
    ['label'=>'Enrollments','count'=>$total_enrollments,'link'=>'enrollments.php','class'=>'card-secondary'],
    ['label'=>'Reports','count'=>$total_reports,'link'=>'reports.php','class'=>'card-warning'],
    ['label'=>'Posts','count'=>$total_posts,'link'=>'posts.php','class'=>'card-info'],
    ['label'=>'Users','count'=>$total_users,'link'=>'users.php','class'=>'card-danger'],
    ['label'=>'Donations','count'=>$total_donations_amount,'link'=>'#donationsSection','class'=>'card-dark'],
    ['label'=>'Feedback','count'=>$total_feedback,'link'=>'feedback.php','class'=>'card-light'],
];

// --- FETCH DONATIONS ---
$donations = $conn->query("
    SELECT d.id,d.amount,d.category,d.status,d.created_at,u.name AS donor_name,p.name AS program_name
    FROM donations d
    LEFT JOIN users u ON d.donor_id=u.ptpID
    LEFT JOIN programs p ON d.program_id=p.id
    ORDER BY d.created_at DESC
")->fetch_all(MYSQLI_ASSOC) ?? [];

// --- CHART DATA ---
$genderData = $conn->query("SELECT gender,COUNT(*) AS c FROM beneficiaries GROUP BY gender")->fetch_all(MYSQLI_ASSOC);
$postData   = $conn->query("SELECT c.category_name,COUNT(p.id) AS total FROM posts p LEFT JOIN post_categories c ON p.category_id=c.id GROUP BY c.category_name")->fetch_all(MYSQLI_ASSOC);
$reportData = $conn->query("SELECT MONTH(report_date) AS month,COUNT(*) AS total FROM reports WHERE report_date IS NOT NULL GROUP BY MONTH(report_date)")->fetch_all(MYSQLI_ASSOC);

// --- FLASH MESSAGE ---
$flash_msg = $_GET['msg'] ?? null;
$flash_type = $_GET['type'] ?? 'success';

include __DIR__.'/header.php';
?>

<style>
.main-content {
    background: #f4f6fa;
    min-height: 100vh;
}

body.dark .main-content {
    background: #0e1117;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 2rem;
}

.welcome-section h2 {
    font-size: 2rem;
    font-weight: 600;
    color: #222;
    margin-bottom: 0.5rem;
}

body.dark .welcome-section h2 {
    color: #fff;
}

.role-tag {
    display: inline-block;
    background: #003087;
    color: #fff;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
}

/* Stats Cards Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #fff;
    border-radius: 15px;
    padding: 1.8rem;
    text-decoration: none;
    display: block;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    text-align: center;
}

body.dark .stat-card {
    background: #1a1f3a;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

body.dark .stat-card:hover {
    box-shadow: 0 8px 25px rgba(0, 48, 135, 0.4);
}

.stat-card.card-primary {
    background: linear-gradient(135deg, #003087 0%, #0059b3 100%);
    color: #fff;
}

.stat-card.card-success {
    background: linear-gradient(135deg, #00d4aa 0%, #00b894 100%);
    color: #fff;
}

.stat-card.card-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: #fff;
}

.stat-card.card-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #fff;
}

.stat-card.card-info {
    background: linear-gradient(135deg, #5b9dff 0%, #4285f4 100%);
    color: #fff;
}

.stat-card.card-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: #fff;
}

.stat-card.card-dark {
    background: linear-gradient(135deg, #343a40 0%, #23272b 100%);
    color: #fff;
}

.stat-card.card-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #212529;
}

body.dark .stat-card.card-light {
    background: linear-gradient(135deg, #2a3150 0%, #1a1f3a 100%);
    color: #fff;
}

.stat-card h6 {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.8rem;
    font-weight: 500;
    opacity: 0.9;
}

.stat-card .counter {
    font-size: 2.8rem;
    font-weight: 700;
}

/* Dashboard Card */
.dashboard-card {
    background: #fff;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 1.5rem;
}

body.dark .dashboard-card {
    background: #1a1f3a;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.dashboard-card h4, .dashboard-card h6 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #003087;
    margin-bottom: 1.5rem;
}

body.dark .dashboard-card h4, body.dark .dashboard-card h6 {
    color: #5b9dff;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Table Styling */
.table-responsive {
    overflow-x: auto;
}

.donation-table {
    width: 100%;
    margin-top: 1rem;
}

.donation-table thead th {
    background: rgba(0, 48, 135, 0.1);
    color: #003087;
    font-weight: 600;
    padding: 1rem;
    border: none;
    font-size: 0.9rem;
    white-space: nowrap;
}

body.dark .donation-table thead th {
    background: rgba(0, 48, 135, 0.2);
    color: #5b9dff;
}

.donation-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
    color: #555;
    vertical-align: middle;
}

body.dark .donation-table tbody td {
    border-bottom-color: #2a3150;
    color: #c5cad9;
}

.donation-table tbody tr:hover {
    background: rgba(0, 48, 135, 0.03);
}

body.dark .donation-table tbody tr:hover {
    background: rgba(0, 48, 135, 0.1);
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.approved {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

body.dark .status-badge.pending {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

body.dark .status-badge.approved {
    background: rgba(91, 157, 255, 0.2);
    color: #5b9dff;
}

body.dark .status-badge.completed {
    background: rgba(0, 212, 170, 0.2);
    color: #00d4aa;
}

body.dark .status-badge.rejected {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

/* Action Buttons */
.btn-primary {
    background: #003087;
    border: none;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: #0059b3;
}

body.dark .btn-primary {
    background: #0059b3;
}

body.dark .btn-primary:hover {
    background: #007bff;
}

.action-dropdown .dropdown-toggle {
    background: #6c757d;
    border: none;
    color: #fff;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all 0.3s;
}

.action-dropdown .dropdown-toggle:hover {
    background: #5a6268;
}

body.dark .action-dropdown .dropdown-toggle {
    background: #495057;
}

body.dark .action-dropdown .dropdown-toggle:hover {
    background: #6c757d;
}

.action-dropdown .dropdown-menu {
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    border-radius: 8px;
}

body.dark .action-dropdown .dropdown-menu {
    background: #1a1f3a;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
}

.action-dropdown .dropdown-item {
    padding: 0.6rem 1.2rem;
    font-size: 0.9rem;
    transition: all 0.2s;
}

body.dark .action-dropdown .dropdown-item {
    color: #c5cad9;
}

.action-dropdown .dropdown-item:hover {
    background: rgba(0, 48, 135, 0.1);
}

body.dark .action-dropdown .dropdown-item:hover {
    background: rgba(0, 48, 135, 0.2);
    color: #fff;
}

/* Alert Styling */
.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

body.dark .alert-success {
    background: rgba(0, 212, 170, 0.2);
    color: #00d4aa;
}

body.dark .alert-danger {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

/* Modal Dark Mode */
body.dark .modal-content {
    background: #1a1f3a;
    color: #c5cad9;
}

body.dark .modal-header {
    border-bottom-color: #2a3150;
}

body.dark .modal-footer {
    border-top-color: #2a3150;
}

body.dark .form-control, body.dark .form-select {
    background: #0e1117;
    border-color: #2a3150;
    color: #c5cad9;
}

body.dark .form-control:focus, body.dark .form-select:focus {
    background: #0e1117;
    border-color: #0059b3;
    color: #c5cad9;
}

body.dark .btn-close {
    filter: invert(1);
}
</style>

<div class="main-content">
  <!-- Welcome Section -->
  <div class="welcome-section">
    <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
    <span class="role-tag">Role: <?= htmlspecialchars($_SESSION['role']) ?></span>
  </div>

  <?php if ($flash_msg): ?>
  <div class="alert alert-<?= $flash_type==='danger'?'danger':'success' ?> alert-dismissible fade show">
      <?= htmlspecialchars($flash_msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div class="stats-row">
    <?php foreach($cards as $card): ?>
      <a href="<?= $card['link'] ?>" class="stat-card <?= $card['class'] ?>">
        <h6><?= htmlspecialchars($card['label']) ?></h6>
        <div class="counter" data-count="<?= htmlspecialchars($card['count']) ?>">0</div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Charts Section -->
  <div class="charts-grid">
    <div class="dashboard-card">
      <h6>Beneficiaries by Gender</h6>
      <canvas id="genderChart" height="200"></canvas>
    </div>
    <div class="dashboard-card">
      <h6>Posts by Category</h6>
      <canvas id="postsChart" height="200"></canvas>
    </div>
  </div>

  <div class="dashboard-card">
    <h6>Reports per Month</h6>
    <canvas id="reportsChart" height="100"></canvas>
  </div>

  <!-- Donations Table -->
  <div class="dashboard-card" id="donationsSection">
    <h4>All Donations</h4>
    <div class="table-responsive">
      <table class="donation-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Donor</th>
            <th>Program</th>
            <th>Amount</th>
            <th>Category</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!empty($donations)): foreach($donations as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['id']) ?></td>
            <td><?= htmlspecialchars($d['donor_name']??'N/A') ?></td>
            <td><?= htmlspecialchars($d['program_name']??'N/A') ?></td>
            <td>UGX <?= number_format($d['amount'],2) ?></td>
            <td><?= htmlspecialchars($d['category']) ?></td>
            <td>
              <?php
                $s=$d['status'];
                $statusClass = strtolower($s);
                if($s==='Completed') echo '<span class="status-badge completed">Completed</span>';
                elseif($s==='Approved') echo '<span class="status-badge approved">Approved</span>';
                elseif($s==='Rejected') echo '<span class="status-badge rejected">Rejected</span>';
                else echo '<span class="status-badge pending">Pending</span>';
              ?>
            </td>
            <td><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
            <td>
              <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $d['id'] ?>">
                <i class="bi bi-pencil"></i> Edit
              </button>
              
              <div class="dropdown action-dropdown d-inline-block">
                <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu">
                  <li>
                    <form method="POST" class="d-inline w-100">
                      <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="quickAction" value="approve">
                      <button type="submit" class="dropdown-item" <?= $d['status']==='Approved'||$d['status']==='Completed'?'disabled':'' ?>>
                        <i class="bi bi-check-circle"></i> Approve
                      </button>
                    </form>
                  </li>
                  <li>
                    <form method="POST" class="d-inline w-100">
                      <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="quickAction" value="complete">
                      <button type="submit" class="dropdown-item" <?= $d['status']==='Completed'?'disabled':'' ?>>
                        <i class="bi bi-check-all"></i> Complete
                      </button>
                    </form>
                  </li>
                  <li>
                    <form method="POST" class="d-inline w-100">
                      <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="quickAction" value="reject">
                      <button type="submit" class="dropdown-item" <?= $d['status']==='Rejected'||$d['status']==='Completed'?'disabled':'' ?>>
                        <i class="bi bi-x-circle"></i> Reject
                      </button>
                    </form>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <form method="POST" class="d-inline w-100" onsubmit="return confirm('Delete this donation?')">
                      <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                      <button type="submit" name="deleteDonation" class="dropdown-item text-danger">
                        <i class="bi bi-trash"></i> Delete
                      </button>
                    </form>
                  </li>
                </ul>
              </div>

              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Donation <?= $d['id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                        <div class="mb-3">
                          <label class="form-label">Amount</label>
                          <input type="number" name="amount" class="form-control" value="<?= $d['amount'] ?>" step="0.01" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Category</label>
                          <select name="category" class="form-select" required>
                            <option value="Money" <?= $d['category']==='Money'?'selected':'' ?>>Money</option>
                            <option value="Food" <?= $d['category']==='Food'?'selected':'' ?>>Food</option>
                            <option value="Clothes" <?= $d['category']==='Clothes'?'selected':'' ?>>Clothes</option>
                            <option value="Others" <?= $d['category']==='Others'?'selected':'' ?>>Others</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Status</label>
                          <select name="status" class="form-select" required>
                            <option value="Pending" <?= $d['status']==='Pending'?'selected':'' ?>>Pending</option>
                            <option value="Approved" <?= $d['status']==='Approved'?'selected':'' ?>>Approved</option>
                            <option value="Completed" <?= $d['status']==='Completed'?'selected':'' ?>>Completed</option>
                            <option value="Rejected" <?= $d['status']==='Rejected'?'selected':'' ?>>Rejected</option>
                          </select>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="updateDonation" class="btn btn-primary">Save Changes</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" class="text-center text-muted">No donations found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Counter animation
document.querySelectorAll('.counter').forEach(counter => {
    let countTo = parseInt(counter.getAttribute('data-count'));
    let count = 0;
    let step = Math.ceil(countTo / 60);
    if(step === 0) step = 1;
    let interval = setInterval(() => {
        count += step;
        if(count >= countTo) { 
            count = countTo; 
            clearInterval(interval); 
        }
        counter.innerText = count.toLocaleString();
    }, 30);
});

// Chart Configuration
const isDark = document.body.classList.contains('dark');
const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
const textColor = isDark ? '#8b92b0' : '#666';

const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            display: true,
            labels: {
                color: textColor,
                padding: 15,
                font: { size: 12 }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { 
                color: gridColor, 
                drawBorder: false 
            },
            ticks: { 
                color: textColor,
                font: { size: 11 }
            }
        },
        x: {
            grid: { 
                color: gridColor, 
                drawBorder: false 
            },
            ticks: { 
                color: textColor,
                font: { size: 11 }
            }
        }
    }
};

// Charts
const genderLabels = <?= json_encode(array_column($genderData,'gender')) ?>;
const genderCounts = <?= json_encode(array_map('intval',array_column($genderData,'c'))) ?>;
const postLabels = <?= json_encode(array_column($postData,'category_name')) ?>;
const postCounts = <?= json_encode(array_map('intval',array_column($postData,'total'))) ?>;
const reportLabels = <?= json_encode(array_map(fn($r)=>date('M',mktime(0,0,0,$r['month'],10)),$reportData)) ?>;
const reportCounts = <?= json_encode(array_map('intval',array_column($reportData,'total'))) ?>;

// Gender Chart
if(document.getElementById('genderChart')) {
    new Chart(document.getElementById('genderChart'), {
        type: 'pie',
        data: {
            labels: genderLabels,
            datasets: [{
                data: genderCounts,
                backgroundColor: ['#003087', '#00d4aa', '#5b9dff', '#ffc107'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        padding: 15,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// Posts Chart
if(document.getElementById('postsChart')) {
    new Chart(document.getElementById('postsChart'), {
        type: 'doughnut',
        data: {
            labels: postLabels,
            datasets: [{
                data: postCounts,
                backgroundColor: ['#003087', '#00d4aa', '#5b9dff', '#ffc107', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        padding: 15,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// Reports Chart
if(document.getElementById('reportsChart')) {
    const ctx = document.getElementById('reportsChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(0, 48, 135, 0.9)');
    gradient.addColorStop(1, 'rgba(0, 89, 179, 0.7)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: reportLabels,
            datasets: [{
                label: 'Reports',
                data: reportCounts,
                backgroundColor: gradient,
                borderColor: '#003087',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: chartOptions
    });
}
</script>