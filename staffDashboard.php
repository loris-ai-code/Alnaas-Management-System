<?php
session_start();
include_once __DIR__.'/alnaasDB.php';

if(!isset($_SESSION['username']) || $_SESSION['role']!=='Staff'){
    header("Location: alnaasLogin.php");
    exit();
}


// Quick Stats with error handling
$result = $conn->query("SELECT COUNT(*) AS c FROM beneficiaries");
$total_beneficiaries = $result ? $result->fetch_assoc()['c'] : 0;

$result = $conn->query("SELECT COUNT(*) AS c FROM programs");
$total_programs = $result ? $result->fetch_assoc()['c'] : 0;

$result = $conn->query("SELECT COUNT(*) AS c FROM reports");
$total_reports = $result ? $result->fetch_assoc()['c'] : 0;

$result = $conn->query("SELECT COUNT(*) AS c FROM program_enrollments");
$total_enrollments = $result ? $result->fetch_assoc()['c'] : 0;

// Charts Data with error handling

// Gender
$result = $conn->query("SELECT gender, COUNT(*) AS c FROM beneficiaries WHERE gender IS NOT NULL GROUP BY gender");
$genderData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Age Group
$result = $conn->query("
    SELECT 
      CASE 
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 5 THEN '0-5'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 12 THEN '6-12'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 18 THEN '13-18'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 35 THEN '19-35'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 60 THEN '36-60'
        ELSE '60+' 
      END AS age_group,
      COUNT(*) AS c
    FROM beneficiaries
    WHERE dob IS NOT NULL
    GROUP BY age_group
");
$ageGroupData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Reports by Month
$result = $conn->query("
    SELECT MONTH(report_date) AS month, COUNT(*) AS total
    FROM reports
    WHERE report_date IS NOT NULL AND YEAR(report_date) = YEAR(CURDATE())
    GROUP BY MONTH(report_date)
    ORDER BY MONTH(report_date)
");
$reportMonthData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Beneficiaries per Program
$result = $conn->query("
    SELECT p.name AS program_name, COUNT(pe.beneficiary_id) AS total_beneficiaries
    FROM programs p
    LEFT JOIN program_enrollments pe ON pe.program_id = p.id
    GROUP BY p.id, p.name
    ORDER BY total_beneficiaries DESC
    LIMIT 5
");
$programBeneficiaries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Recent Feedback
$result = $conn->query("
    SELECT f.*, b.first_name, b.last_name
    FROM feedback f
    LEFT JOIN beneficiaries b ON f.beneficiary_id = b.id
    ORDER BY f.created_at DESC
    LIMIT 5
");
$feedbackData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Recent Notifications
$result = $conn->query("
    SELECT * FROM notifications 
    WHERE status='unread' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$notificationsData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__.'/header.php';

// Prepare JS arrays
$genderLabels = array_column($genderData,'gender');
$genderCounts = array_map('intval', array_column($genderData,'c'));

$ageLabels = array_column($ageGroupData,'age_group');
$ageCounts = array_map('intval', array_column($ageGroupData,'c'));

$monthLabels = array_map(fn($r)=>date('M', mktime(0,0,0,$r['month'],10)),$reportMonthData);
$monthCounts = array_map('intval', array_column($reportMonthData,'total'));

$programLabels = array_column($programBeneficiaries,'program_name');
$programCounts = array_map('intval', array_column($programBeneficiaries,'total_beneficiaries'));
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

/* Notifications Panel */
.notifications-panel {
    max-height: 600px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem;
    border-left: 3px solid #003087;
    margin-bottom: 1rem;
    background: rgba(0, 48, 135, 0.05);
    border-radius: 6px;
    display: flex;
    align-items: start;
    gap: 0.8rem;
}

body.dark .notification-item {
    background: rgba(0, 48, 135, 0.15);
    border-left-color: #5b9dff;
}

.notification-item .dot {
    width: 8px;
    height: 8px;
    background: #00d4aa;
    border-radius: 50%;
    margin-top: 0.5rem;
    flex-shrink: 0;
}

.notification-item p {
    margin: 0;
    color: #555;
    font-size: 0.9rem;
    line-height: 1.5;
}

body.dark .notification-item p {
    color: #c5cad9;
}

/* Suggested Programs */
.program-card {
    background: linear-gradient(135deg, rgba(0, 48, 135, 0.1) 0%, rgba(0, 89, 179, 0.1) 100%);
    border: 1px solid rgba(0, 48, 135, 0.2);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

body.dark .program-card {
    background: rgba(0, 48, 135, 0.15);
    border-color: rgba(0, 48, 135, 0.3);
}

.program-card h5 {
    color: #003087;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

body.dark .program-card h5 {
    color: #5b9dff;
}

.program-card p {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

body.dark .program-card p {
    color: #8b92b0;
}

.program-card .btn {
    background: #003087;
    color: #fff;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
}

.program-card .btn:hover {
    background: #0059b3;
    transform: translateY(-2px);
}

/* Table Styling */
.feedback-table {
    width: 100%;
}

.feedback-table thead th {
    background: rgba(0, 48, 135, 0.1);
    color: #003087;
    font-weight: 600;
    padding: 1rem;
    border: none;
    font-size: 0.9rem;
}

body.dark .feedback-table thead th {
    background: rgba(0, 48, 135, 0.2);
    color: #5b9dff;
}

.feedback-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
    color: #555;
}

body.dark .feedback-table tbody td {
    border-bottom-color: #2a3150;
    color: #c5cad9;
}

.feedback-table tbody tr:hover {
    background: rgba(0, 48, 135, 0.03);
}

body.dark .feedback-table tbody tr:hover {
    background: rgba(0, 48, 135, 0.1);
}

/* Scrollbar */
.notifications-panel::-webkit-scrollbar {
    width: 6px;
}

.notifications-panel::-webkit-scrollbar-track {
    background: #f4f6fa;
}

body.dark .notifications-panel::-webkit-scrollbar-track {
    background: #1a1f3a;
}

.notifications-panel::-webkit-scrollbar-thumb {
    background: #003087;
    border-radius: 10px;
}

.notifications-panel::-webkit-scrollbar-thumb:hover {
    background: #0059b3;
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
    <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
    <span class="role-tag">Role: <?= htmlspecialchars($_SESSION['role']) ?></span>
  </div>

  <!-- Stats Cards -->
  <div class="stats-row">
    <a href="beneficiaries.php" class="stat-card card-primary">
      <h6>Beneficiaries</h6>
      <div class="counter" data-count="<?= $total_beneficiaries ?>">0</div>
    </a>
    <a href="programs.php" class="stat-card card-success">
      <h6>Programs</h6>
      <div class="counter" data-count="<?= $total_programs ?>">0</div>
    </a>
    <a href="reports.php" class="stat-card card-warning">
      <h6>Reports</h6>
      <div class="counter" data-count="<?= $total_reports ?>">0</div>
    </a>
    <a href="enrollments.php" class="stat-card card-info">
      <h6>Enrollments</h6>
      <div class="counter" data-count="<?= $total_enrollments ?>">0</div>
    </a>
  </div>

  <!-- Main Dashboard Grid -->
  <div class="dashboard-grid">
    <!-- Left Column: Chart -->
    <div class="dashboard-card">
      <h6>Reports by Month</h6>
      <canvas id="reportMonthChart" height="100"></canvas>
    </div>

    <!-- Right Column: Notifications -->
    <div class="dashboard-card">
      <h6>Notifications</h6>
      <div class="notifications-panel">
        <?php if(empty($notificationsData)): ?>
          <div class="empty-state">
            <p>No new notifications</p>
          </div>
        <?php else: ?>
          <?php foreach($notificationsData as $notif): ?>
            <div class="notification-item">
              <div class="dot"></div>
              <p><?= htmlspecialchars($notif['message']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <a href="notifications.php" class="btn btn-sm mt-3 w-100" style="background: #003087; color: #fff; text-decoration: none; display: inline-block; text-align: center; padding: 0.6rem; border-radius: 8px;">View All Notifications</a>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="charts-grid">
    <div class="dashboard-card">
      <h6>Beneficiaries by Gender</h6>
      <canvas id="genderChart" height="100"></canvas>
    </div>
    <div class="dashboard-card">
      <h6>Beneficiaries by Age Group</h6>
      <canvas id="ageGroupChart" height="100"></canvas>
    </div>
  </div>

  <!-- Program Stats -->
  <div class="dashboard-card mb-3">
    <h6>Top Programs by Enrollments</h6>
    <canvas id="programChart" height="80"></canvas>
  </div>

  <!-- Suggested Programs Section -->
  <div class="dashboard-grid">
    <div class="dashboard-card">
      <h6>Recent Feedback</h6>
      <?php if(empty($feedbackData)): ?>
        <div class="empty-state">
          <p>No feedback available</p>
        </div>
      <?php else: ?>
        <table class="feedback-table">
          <thead>
            <tr>
              <th>Beneficiary</th>
              <th>Feedback</th>
              <th>Rating</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($feedbackData as $f): ?>
              <tr>
                <td><?= $f['anonymous'] ? 'Anonymous' : htmlspecialchars($f['first_name'].' '.$f['last_name']) ?></td>
                <td><?= htmlspecialchars(substr($f['feedback_text'], 0, 50)) ?>...</td>
                <td>
                  <?php if($f['rating']): ?>
                    <span style="color: #ffc107;">
                      <?= str_repeat('★', intval($f['rating'])) ?>
                    </span>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td><?= date('M d', strtotime($f['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="dashboard-card">
      <h6>Suggested Programs</h6>
      <div class="program-card">
        <h5>Community Health Initiative</h5>
        <p>Providing essential health services to underserved communities</p>
        <button class="btn">Learn More</button>
      </div>
      <div class="program-card">
        <h5>Education Support Program</h5>
        <p>Supporting children's education through scholarships and resources</p>
        <button class="btn">Learn More</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__.'/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

document.querySelectorAll('.counter').forEach(counter=>{
    let countTo = parseInt(counter.getAttribute('data-count'));
    let count = 0;
    let step = Math.ceil(countTo / 60);
    if(step === 0) step = 1;
    let interval = setInterval(()=>{
        count += step;
        if(count >= countTo){ count = countTo; clearInterval(interval); }
        counter.innerText = count;
    }, 30);
});



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

// Reports by Month (Bar Chart)
if(document.getElementById('reportMonthChart')) {
    const ctx1 = document.getElementById('reportMonthChart').getContext('2d');
    const gradient1 = ctx1.createLinearGradient(0, 0, 0, 300);
    gradient1.addColorStop(0, 'rgba(0, 48, 135, 0.9)');
    gradient1.addColorStop(1, 'rgba(0, 89, 179, 0.7)');

    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Reports',
                data: <?= json_encode($monthCounts) ?>,
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

// Gender Distribution (Doughnut)
if(document.getElementById('genderChart')) {
    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($genderLabels) ?>,
            datasets: [{
                data: <?= json_encode($genderCounts) ?>,
                backgroundColor: [
                    '#003087',
                    '#00d4aa',
                    '#5b9dff',
                    '#ffc107'
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

// Age Groups (Bar Chart)
if(document.getElementById('ageGroupChart')) {
    const ctx3 = document.getElementById('ageGroupChart').getContext('2d');
    const gradient3 = ctx3.createLinearGradient(0, 0, 0, 300);
    gradient3.addColorStop(0, 'rgba(0, 212, 170, 0.9)');
    gradient3.addColorStop(1, 'rgba(0, 212, 170, 0.5)');

    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?= json_encode($ageLabels) ?>,
            datasets: [{
                label: 'Beneficiaries',
                data: <?= json_encode($ageCounts) ?>,
                backgroundColor: gradient3,
                borderColor: '#00d4aa',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: chartOptions
    });
}

// Programs (Horizontal Bar)
if(document.getElementById('programChart')) {
    const ctx4 = document.getElementById('programChart').getContext('2d');
    const gradient4 = ctx4.createLinearGradient(0, 0, 500, 0);
    gradient4.addColorStop(0, 'rgba(255, 193, 7, 0.9)');
    gradient4.addColorStop(1, 'rgba(255, 193, 7, 0.5)');

    new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: <?= json_encode($programLabels) ?>,
            datasets: [{
                label: 'Enrollments',
                data: <?= json_encode($programCounts) ?>,
                backgroundColor: gradient4,
                borderColor: '#ffc107',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            ...chartOptions,
            indexAxis: 'y'
        }
    });
}
</script>