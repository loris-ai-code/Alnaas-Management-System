<?php
session_start();
include __DIR__.'/alnaasDB.php';

// Check login & donor role
if(!isset($_SESSION['username']) || $_SESSION['role'] != 'Donor'){
    header("Location: alnaasLogin.php");
    exit();
}

// --- PHP Data Fetching (Robust and Secure) ---
$donor_id = $_SESSION['ptpID'] ?? 0;
$donor_username = $_SESSION['username'] ?? 'Donor';


// 1. Fetch Stats (Robust Error Checking)
$result_programs = $conn->query("SELECT COUNT(*) AS c FROM programs");
$total_programs = ($result_programs && $result_programs->num_rows > 0) ? $result_programs->fetch_assoc()['c'] : 0;

$result_reports = $conn->query("SELECT COUNT(*) AS c FROM reports");
$total_reports = ($result_reports && $result_reports->num_rows > 0) ? $result_reports->fetch_assoc()['c'] : 0;

$result_posts = $conn->query("SELECT COUNT(*) AS c FROM posts");
$total_posts = ($result_posts && $result_posts->num_rows > 0) ? $result_posts->fetch_assoc()['c'] : 0;

// Get total number of donations made by the current donor
$query_total_donations = "SELECT COUNT(*) AS c FROM donations WHERE donor_id=? AND status='Completed'";
if ($stmt = $conn->prepare($query_total_donations)) {
    $stmt->bind_param("i", $donor_id);
    $stmt->execute();
    $total_donations = $stmt->get_result()->fetch_assoc()['c'] ?? 0;
    $stmt->close();
} else {
    $total_donations = 0;
}


// KPI Cards Array (Matching the Admin Dashboard structure)
$cards = [
    ['label'=>'Total Programs','count'=>$total_programs,'link'=>'programs.php','class'=>'text-bg-primary'],
    ['label'=>'My Donations','count'=>$total_donations,'link'=>'myDonation.php','class'=>'text-bg-success'],
    ['label'=>'Reports Available','count'=>$total_reports,'link'=>'donorReports.php','class'=>'text-bg-warning text-dark'],
    ['label'=>'News Posts','count'=>$total_posts,'link'=>'posts.php','class'=>'text-bg-info text-dark'],
];


// 2. Donations by Category (for Chart)
$donationData = [];
$query_donations = "SELECT category, SUM(amount) AS total FROM donations WHERE donor_id=? AND status='Completed' GROUP BY category";
if ($stmt_donations = $conn->prepare($query_donations)) {
    $stmt_donations->bind_param("i", $donor_id);
    $stmt_donations->execute();
    $res = $stmt_donations->get_result();
    if($res) $donationData = $res->fetch_all(MYSQLI_ASSOC);
    $stmt_donations->close();
}

$donationLabels = array_column($donationData,'category');
$donationTotals = array_map('floatval', array_column($donationData,'total'));
$chartColors = ['#0d6efd', '#198754', '#fd7e14', '#dc3545', '#6c757d'];


// 3. Recent Donations (for Table)
$recentDonations = [];
$query_recent = "SELECT d.amount, d.category, d.status, d.created_at, p.name AS program_name
                 FROM donations d
                 LEFT JOIN programs p ON d.program_id=p.id
                 WHERE d.donor_id=?
                 ORDER BY d.created_at DESC LIMIT 5";
if ($stmt_recent = $conn->prepare($query_recent)) {
    $stmt_recent->bind_param("i", $donor_id);
    $stmt_recent->execute();
    $res2 = $stmt_recent->get_result();
    if($res2) $recentDonations = $res2->fetch_all(MYSQLI_ASSOC);
    $stmt_recent->close();
}


// 4. Recent Donor Activity (For the right side card)
$recentActivity = [
    ['icon' => 'bi-hand-thumbs-up', 'color' => 'success', 'message' => "Your recent donation has been successfully **approved**."],
    ['icon' => 'bi-megaphone', 'color' => 'info', 'message' => "The **Quarterly Report** for Q3 is now available in your Reports section."],
    ['icon' => 'bi-wallet', 'color' => 'primary', 'message' => "A new program, **'Clean Water Initiative'**, is now open for donations."],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALNAAS Donor Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { background:#f4f6fa; font-family:'Poppins', sans-serif; color:#222; transition:background 0.4s, color 0.4s; }
.sidebar { height:100vh; width:250px; position:fixed; top:0; left:0; background:linear-gradient(180deg,#003087,#0059b3); color:white; padding-top:20px; transition:0.4s; }
.sidebar img.logo { width:100px; height:100px; object-fit:cover; border-radius:50%; margin-bottom:12px; }
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; border-radius:8px; margin:5px 10px; transition:0.3s; }
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff; }

.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); transition:0.3s; }
.dashboard-card { cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:140px; text-align:center; padding:25px; transition:0.3s; }
.dashboard-card:hover { transform:translateY(-6px) scale(1.03); box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.card-link { text-decoration:none; color:inherit; display:block; height:100%; }
.counter { font-size:2rem; font-weight:700; }

.chart-card { cursor:pointer; transition:transform 0.3s ease, box-shadow 0.3s ease; }
.chart-card:hover { transform:translateY(-5px) scale(1.03); box-shadow:0 10px 25px rgba(0,0,0,0.15); }

/* Dark Mode from alnaasDashboard.php */
body.dark { background:#0e1117; color:#eee; }
.dark .sidebar { background:linear-gradient(180deg,#156eccff,#1b263b); }
.dark .sidebar a { color:#aaa; }
.dark .sidebar a:hover, .dark .sidebar a.active { background:rgba(46,14,14,0.1); color:#fff; }
.dark .card { background:#1e1e1e; color:#ddd; box-shadow:0 6px 20px rgba(255,255,255,0.05); }
</style>
</head>
<body>

<?php 
// Assuming header.php contains the sidebar and topbar structure
// (including the theme toggle and notification button)
include __DIR__.'/header.php'; 
?>

<div class="main-content">
    <h2 class="fw-bold mb-2">Welcome, <?= htmlspecialchars($donor_username) ?>!</h2>
    <p class="text-muted mb-4">Role: <span class="badge bg-success"><?= htmlspecialchars($_SESSION['role']) ?></span></p>

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

    <div class="row g-3">
        <div class="col-md-6 col-lg-5">
            <div class="card p-3 text-center chart-card" onclick="window.location.href='myDonations.php'">
                <h6>Your Donations by Category</h6>
                <div style="position:relative; height:220px; width:220px; margin:auto;">
                    <canvas id="donationChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-7">
            <div class="card p-3 chart-card" style="height: 100%; cursor: default;">
                <h6 class="mb-3"><i class="bi bi-activity me-1 text-primary"></i> Recent Updates for You</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach($recentActivity as $activity): ?>
                        <li class="list-group-item d-flex align-items-center border-0 py-2" style="background: none;">
                            <i class="bi <?= $activity['icon'] ?> me-2 text-<?= $activity['color'] ?>" style="font-size: 1.2rem;"></i>
                            <p class="mb-0 small"><?= $activity['message'] ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-12">
            <div class="card p-3">
                <h6 class="mb-3">Recent Donations Status</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Category</th>
                                <th>Program</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($recentDonations as $d):
                            $status_class = match(strtolower($d['status'])) {
                                'completed' => 'text-success',
                                'pending' => 'text-warning',
                                'failed' => 'text-danger',
                                default => 'text-secondary',
                            };
                        ?>
                            <tr>
                                <td>$<?= number_format($d['amount'], 0) ?></td>
                                <td><?= htmlspecialchars($d['category']) ?></td>
                                <td><?= htmlspecialchars($d['program_name'] ?? 'N/A') ?></td>
                                <td class="<?= $status_class ?>"><?= htmlspecialchars($d['status']) ?></td>
                                <td><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recentDonations)) echo '<tr><td colspan="5" class="text-center text-muted">No recent donations.</td></tr>'; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Assuming footer.php contains closing tags and universal JS like the theme toggle
include __DIR__.'/footer.php'; 
?>

<script>
// --- Chart.js Initialization ---
const donationChartCtx = document.getElementById('donationChart');
if(donationChartCtx) {
    const isDark = document.body.classList.contains('dark');
    
    new Chart(donationChartCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($donationLabels) ?>,
            datasets: [{
                data: <?= json_encode($donationTotals) ?>,
                backgroundColor: <?= json_encode($chartColors) ?>,
                // Border color is set dynamically based on theme for contrast
                borderColor: isDark ? '#1e1e1e' : '#fff', 
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: isDark ? '#eee' : '#222'
                    }
                }
            }
        }
    });
}


// --- Theme Toggle Integration (Ensure this logic is also in the footer.php if possible) ---
function updateChartTheme(isDark) {
    const chart = Chart.getChart('donationChart');
    if (chart) {
        chart.options.plugins.legend.labels.color = isDark ? '#eee' : '#222';
        chart.data.datasets[0].borderColor = isDark ? '#1e1e1e' : '#fff';
        chart.update();
    }
}
// Listen for theme changes (assuming the universal theme toggle script is loaded by footer.php)
const themeToggle = document.getElementById('themeToggle');
if(themeToggle) {
    themeToggle.addEventListener('click', () => {
        // Toggle happens first, then check class
        updateChartTheme(document.body.classList.contains('dark'));
    });
}


// --- Counter Animation Script ---
// Note: This script is copied from your alnaasDashboard.php script block
document.querySelectorAll('.counter').forEach(counter=>{
    let countTo=parseInt(counter.getAttribute('data-count'));
    if (isNaN(countTo) || countTo === 0) return;
    
    let count=0;
    let step=Math.ceil(countTo/100);
    let interval=setInterval(()=>{
        count+=step;
        if(count>=countTo){count=countTo; clearInterval(interval);}
        counter.innerText=count.toLocaleString(); // Add .toLocaleString() for commas
    },20);
});
</script>
</body>
</html>