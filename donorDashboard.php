<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'alnaasDB.php';
include 'languages.php'; 

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'Donor') {
    header("Location: alnaasLogin.php");
    exit();
}

$userId = $_SESSION['ptpID'] ?? 0;
$username = $_SESSION['username'] ?? 'Guest';
$current_lang = $_SESSION['language'] ?? 'en';

include 'header.php';

// Safe query function
function safeQuery($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : [];
}

// --- Donation Summary ---
$totalDonated = safeQuery($conn, "SELECT SUM(amount) as total FROM donations WHERE donor_id = $userId")['total'] ?? 0;
$lastDonation = safeQuery($conn, "SELECT amount, created_at FROM donations WHERE donor_id = $userId ORDER BY created_at DESC LIMIT 1");
$pendingPledges = safeQuery($conn, "SELECT COUNT(*) as c FROM donations WHERE donor_id = $userId AND status='Pending'")['c'] ?? 0;
$programsSupported = safeQuery($conn, "SELECT COUNT(DISTINCT program_id) as c FROM donations WHERE donor_id = $userId")['c'] ?? 0;

// --- Top Programs ---
$topPrograms = $conn->query("
    SELECT p.name, SUM(d.amount) as total
    FROM donations d
    LEFT JOIN programs p ON d.program_id = p.id
    WHERE d.donor_id = $userId
    GROUP BY p.id
    ORDER BY total DESC
    LIMIT 5
");

// --- Donations by Category ---
$categoryData = $conn->query("
    SELECT category, SUM(amount) as total
    FROM donations
    WHERE donor_id = $userId
    GROUP BY category
")->fetch_all(MYSQLI_ASSOC);

$categories = ['Money'=>0,'Food'=>0,'Clothes'=>0,'Others'=>0];
foreach($categoryData as $c){
    $categories[$c['category']] = $c['total'];
}

// --- Monthly Donations ---
$monthlyData = $conn->query("
    SELECT MONTH(created_at) as month, SUM(amount) as total
    FROM donations
    WHERE donor_id = $userId AND YEAR(created_at)=YEAR(CURRENT_DATE)
    GROUP BY MONTH(created_at)
")->fetch_all(MYSQLI_ASSOC);

$monthlyLabels = [];
$monthlyValues = [];
for($i=1;$i<=12;$i++){
    $monthlyLabels[] = date("F", mktime(0,0,0,$i,1));
    $found = false;
    foreach($monthlyData as $m){
        if($m['month']==$i){
            $monthlyValues[] = $m['total'];
            $found = true;
            break;
        }
    }
    if(!$found) $monthlyValues[] = 0;
}

// --- Recent Donations ---
$recentDonations = $conn->query("
    SELECT d.*, p.name as program_name
    FROM donations d
    LEFT JOIN programs p ON d.program_id = p.id
    WHERE d.donor_id = $userId
    ORDER BY d.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// RTL support for Arabic
$isRTL = $current_lang === 'ar';
?>

<style>
<?php if($isRTL): ?>
body {
    direction: rtl;
    text-align: right;
}

.main-content {
    margin-left: 0;
    margin-right: 260px;
}

.sidebar {
    left: auto;
    right: 0;
}

.topbar {
    right: auto;
    left: 25px;
}

.hamburger-btn {
    left: auto;
    right: 15px;
}

@media (max-width: 768px) {
    .main-content {
        margin-right: 0;
    }
    
    .topbar {
        left: 15px;
    }
}
<?php endif; ?>

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

.stat-card.card-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #fff;
}

.stat-card.card-info {
    background: linear-gradient(135deg, #5b9dff 0%, #4285f4 100%);
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

body.dark .stat-card:not(.card-primary):not(.card-success):not(.card-warning):not(.card-info) h6 {
    color: #8b92b0;
}

body.dark .stat-card:not(.card-primary):not(.card-success):not(.card-warning):not(.card-info) .counter {
    color: #fff;
}

/* Main Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

/* Card Styling */
.dashboard-card {
    background: #fff;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

body.dark .dashboard-card {
    background: #1a1f3a;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.dashboard-card h6 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #003087;
    margin-bottom: 1.5rem;
}

body.dark .dashboard-card h6 {
    color: #5b9dff;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

/* Quick Actions Panel */
.quick-actions-panel {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 1rem 1.2rem;
    background: rgba(0, 48, 135, 0.1);
    border: 1px solid rgba(0, 48, 135, 0.2);
    border-radius: 12px;
    text-decoration: none;
    color: #003087;
    font-weight: 500;
    transition: all 0.3s;
}

body.dark .quick-action-btn {
    background: rgba(0, 48, 135, 0.15);
    border-color: rgba(0, 48, 135, 0.3);
    color: #5b9dff;
}

.quick-action-btn:hover {
    background: #003087;
    color: #fff;
    transform: translateX(5px);
}

<?php if($isRTL): ?>
.quick-action-btn:hover {
    transform: translateX(-5px);
}
<?php endif; ?>

body.dark .quick-action-btn:hover {
    background: #0059b3;
}

.quick-action-btn.btn-primary {
    background: linear-gradient(135deg, #003087 0%, #0059b3 100%);
    color: #fff;
    border: none;
}

.quick-action-btn.btn-primary:hover {
    background: linear-gradient(135deg, #0059b3 0%, #007bff 100%);
    transform: translateX(5px) translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 48, 135, 0.3);
}

<?php if($isRTL): ?>
.quick-action-btn.btn-primary:hover {
    transform: translateX(-5px) translateY(-2px);
}
<?php endif; ?>

.quick-action-btn i {
    font-size: 1.2rem;
}

/* Program Cards */
.program-card {
    padding: 1.2rem;
    border-left: 4px solid #003087;
    background: rgba(0, 48, 135, 0.05);
    border-radius: 8px;
    margin-bottom: 1rem;
}

<?php if($isRTL): ?>
.program-card {
    border-left: none;
    border-right: 4px solid #003087;
}
<?php endif; ?>

body.dark .program-card {
    background: rgba(0, 48, 135, 0.15);
    border-left-color: #5b9dff;
}

<?php if($isRTL): ?>
body.dark .program-card {
    border-right-color: #5b9dff;
}
<?php endif; ?>

.program-card h5 {
    font-size: 1rem;
    font-weight: 600;
    color: #003087;
    margin: 0 0 0.5rem 0;
}

body.dark .program-card h5 {
    color: #5b9dff;
}

.program-card small {
    color: #666;
    font-size: 0.85rem;
}

body.dark .program-card small {
    color: #8b92b0;
}

/* Table Styling */
.donation-table {
    width: 100%;
}

.donation-table thead th {
    background: rgba(0, 48, 135, 0.1);
    color: #003087;
    font-weight: 600;
    padding: 1rem;
    border: none;
    font-size: 0.9rem;
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

.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

body.dark .status-badge.pending {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

body.dark .status-badge.completed {
    background: rgba(0, 212, 170, 0.2);
    color: #00d4aa;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #999;
}

body.dark .empty-state {
    color: #666;
}
</style>

<div class="main-content">
  <!-- Welcome Section -->
  <div class="welcome-section">
    <h2><?= t('welcome') ?>, <?= htmlspecialchars($username) ?>!</h2>
    <span class="role-tag"><?= t('role_donor') ?></span>
  </div>

  <!-- Stats Cards -->
  <div class="stats-row">
    <div class="stat-card card-primary">
      <h6><?= t('total_donated') ?></h6>
      <div class="counter" data-count="<?= $totalDonated ?>">0</div>
    </div>
    <div class="stat-card card-warning">
      <h6><?= t('pending_pledges') ?></h6>
      <div class="counter" data-count="<?= $pendingPledges ?>">0</div>
    </div>
    <div class="stat-card card-success">
      <h6><?= t('programs_supported') ?></h6>
      <div class="counter" data-count="<?= $programsSupported ?>">0</div>
    </div>
    <div class="stat-card card-info">
      <h6><?= t('last_donation') ?></h6>
      <div class="counter" data-count="<?= $lastDonation['amount'] ?? 0 ?>">0</div>
    </div>
  </div>

  <!-- Main Dashboard Grid -->
  <div class="dashboard-grid">
    <!-- Left Column: Monthly Donations Chart -->
    <div class="dashboard-card">
      <h6><?= t('monthly_donations') ?></h6>
      <canvas id="monthlyDonationsChart" height="100"></canvas>
    </div>

    <!-- Right Column: Quick Actions -->
    <div class="dashboard-card">
      <h6><?= t('quick_actions') ?></h6>
      <div class="quick-actions-panel">
        <a href="myDonation.php" class="quick-action-btn btn-primary">
          <i class="bi bi-cash-stack"></i>
          <span><?= t('donate_now') ?></span>
        </a>
        <a href="programs.php" class="quick-action-btn">
          <i class="bi bi-calendar-event"></i>
          <span><?= t('view_programs') ?></span>
        </a>
        <a href="reports.php" class="quick-action-btn">
          <i class="bi bi-file-text"></i>
          <span><?= t('view_reports') ?></span>
        </a>
        <a href="reports.php?export=pdf" class="quick-action-btn">
          <i class="bi bi-download"></i>
          <span><?= t('download_pdf') ?></span>
        </a>
        <a href="reports.php?export=excel" class="quick-action-btn">
          <i class="bi bi-file-excel"></i>
          <span><?= t('download_excel') ?></span>
        </a>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="charts-grid">
    <div class="dashboard-card">
      <h6><?= t('donations_by_category') ?></h6>
      <canvas id="categoryChart" height="150"></canvas>
    </div>
    <div class="dashboard-card">
      <h6><?= t('top_programs') ?></h6>
      <?php if($topPrograms->num_rows > 0): ?>
        <?php while($prog = $topPrograms->fetch_assoc()): ?>
          <div class="program-card">
            <h5><?= htmlspecialchars($prog['name']) ?></h5>
            <small><?= t('donated') ?>: UGX <?= number_format($prog['total'], 2) ?></small>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">
          <p><?= t('no_programs_yet') ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Donations Table -->
  <div class="dashboard-card">
    <h6><?= t('recent_donations') ?></h6>
    <?php if(!empty($recentDonations)): ?>
      <table class="donation-table">
        <thead>
          <tr>
            <th><?= t('date') ?></th>
            <th><?= t('category') ?></th>
            <th><?= t('program') ?></th>
            <th><?= t('amount') ?></th>
            <th><?= t('payment_method') ?></th>
            <th><?= t('status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($recentDonations as $d): ?>
            <tr>
              <td><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
              <td><?= htmlspecialchars($d['category']) ?></td>
              <td><?= htmlspecialchars($d['program_name'] ?? t('general')) ?></td>
              <td>UGX <?= number_format($d['amount'], 2) ?></td>
              <td><?= htmlspecialchars($d['payment_method'] ?? 'N/A') ?></td>
              <td>
                <span class="status-badge <?= strtolower($d['status'] ?? 'pending') ?>">
                  <?= htmlspecialchars($d['status'] ?? t('pending')) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <p><?= t('no_donations_yet') ?></p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>

<!-- Charts.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Counter Animation
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

// Monthly Donations Chart
if(document.getElementById('monthlyDonationsChart')) {
    const ctx1 = document.getElementById('monthlyDonationsChart').getContext('2d');
    const gradient1 = ctx1.createLinearGradient(0, 0, 0, 300);
    gradient1.addColorStop(0, 'rgba(0, 48, 135, 0.9)');
    gradient1.addColorStop(1, 'rgba(0, 89, 179, 0.7)');

    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: '<?= t('monthly_donations') ?>',
                data: <?= json_encode($monthlyValues) ?>,
                backgroundColor: gradient1,
                borderColor: '#003087',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: chartOptions
    });
}

// Category Chart
if(document.getElementById('categoryChart')) {
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: [
                '<?= t('money') ?>',
                '<?= t('food') ?>',
                '<?= t('clothes') ?>',
                '<?= t('others') ?>'
            ],
            datasets: [{
                data: <?= json_encode(array_values($categories)) ?>,
                backgroundColor: [
                    '#003087',
                    '#ffc107',
                    '#5b9dff',
                    '#00d4aa'
                ],
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
</script>