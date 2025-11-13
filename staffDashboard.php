<?php
session_start();
include_once __DIR__.'/alnaasDB.php';

if(!isset($_SESSION['username']) || $_SESSION['role']!=='Staff'){
    header("Location: alnaasLogin.php");
    exit();
}

// STAFF DASHBOARD DATA

// Quick Stats
$total_beneficiaries = $conn->query("SELECT COUNT(*) AS c FROM beneficiaries")->fetch_assoc()['c'] ?? 0;
$total_programs = $conn->query("SELECT COUNT(*) AS c FROM programs")->fetch_assoc()['c'] ?? 0;
$total_reports = $conn->query("SELECT COUNT(*) AS c FROM reports")->fetch_assoc()['c'] ?? 0;
$total_notifications = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE status='unread'")->fetch_assoc()['c'] ?? 0;

// Charts Data

// Gender
$genderData = $conn->query("SELECT gender, COUNT(*) AS c FROM beneficiaries GROUP BY gender")->fetch_all(MYSQLI_ASSOC);

// Age Group
$ageGroupData = $conn->query("
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
    GROUP BY age_group
")->fetch_all(MYSQLI_ASSOC);

// Reports by Month
$reportMonthData = $conn->query("
    SELECT MONTH(report_date) AS month, COUNT(*) AS total
    FROM reports
    WHERE report_date IS NOT NULL
    GROUP BY MONTH(report_date)
")->fetch_all(MYSQLI_ASSOC);

// Beneficiaries per Program
$programBeneficiaries = $conn->query("
    SELECT p.name AS program_name, COUNT(b.id) AS total_beneficiaries
    FROM programs p
    LEFT JOIN beneficiaries b ON b.program_id = p.id
    GROUP BY p.id
")->fetch_all(MYSQLI_ASSOC);

// Feedback
$feedbackData = $conn->query("
    SELECT f.*, b.first_name, b.last_name
    FROM feedback f
    LEFT JOIN beneficiaries b ON f.beneficiary_id = b.id
    ORDER BY f.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

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

<div class="main-content">
  <h2 class="fw-bold mb-2">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
  <p class="text-muted mb-4">Role: <span class="badge bg-primary"><?= htmlspecialchars($_SESSION['role']) ?></span></p>

  <!-- Quick Stats Cards -->
  <div class="row g-4 mb-4">
    <?php $cards=[
      ['label'=>'Beneficiaries','count'=>$total_beneficiaries,'link'=>'beneficiaries.php','class'=>'text-bg-primary'],
      ['label'=>'Programs','count'=>$total_programs,'link'=>'programs.php','class'=>'text-bg-success'],
      ['label'=>'Reports','count'=>$total_reports,'link'=>'reports.php','class'=>'text-bg-warning text-dark'],
      ['label'=>'Notifications','count'=>$total_notifications,'link'=>'notifications.php','class'=>'text-bg-secondary']
    ];
    foreach($cards as $c): ?>
      <div class="col-md-3">
        <a href="<?= $c['link'] ?>" class="card-link">
          <div class="card dashboard-card <?= $c['class'] ?>">
            <h6><?= $c['label'] ?></h6>
            <div class="counter" data-count="<?= $c['count'] ?>">0</div>
         
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Section -->
  <div class="row g-4">
    <div class="col-md-6"><div class="card p-3"><h6>Beneficiaries by Gender</h6><canvas id="genderChart"></canvas></div></div>
    <div class="col-md-6"><div class="card p-3"><h6>Beneficiaries by Age Group</h6><canvas id="ageGroupChart"></canvas></div></div>
    <div class="col-md-6"><div class="card p-3"><h6>Reports per Month</h6><canvas id="reportMonthChart"></canvas></div></div>
    <div class="col-md-12"><div class="card p-3"><h6>Beneficiaries per Program</h6><canvas id="programChart"></canvas></div></div>
  </div>

  <!-- Feedback Section -->
  <div class="row g-4 mt-4">
    <div class="col-md-12"><div class="card p-3">
      <h6>Recent Beneficiary Feedback</h6>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-primary">
            <tr><th>Beneficiary</th><th>Feedback</th><th>Rating</th><th>Anonymous</th><th>Date</th></tr>
          </thead>
          <tbody>
          <?php foreach($feedbackData as $f): ?>
            <tr>
              <td><?= $f['anonymous'] ? 'Anonymous' : htmlspecialchars($f['first_name'].' '.$f['last_name']) ?></td>
              <td><?= htmlspecialchars($f['feedback_text']) ?></td>
              <td><?= $f['rating'] ?? '-' ?></td>
              <td><?= $f['anonymous'] ? 'Yes' : 'No' ?></td>
              <td><?= $f['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<?php include __DIR__.'/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ----------------------
// Counters
// ----------------------
document.querySelectorAll('.counter').forEach(counter=>{
    let countTo = parseInt(counter.getAttribute('data-count'));
    let count = 0;
    let step = Math.ceil(countTo / 100);
    let interval = setInterval(()=>{
        count += step;
        if(count >= countTo){ count = countTo; clearInterval(interval); }
        counter.innerText = count;
    }, 20);
});

// ----------------------
// Charts
// ----------------------
const chartOptions = { responsive: true, plugins: { legend: { display: true } } };

// Beneficiaries by Gender
new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($genderLabels) ?>,
        datasets: [{
            data: <?= json_encode($genderCounts) ?>,
            backgroundColor: ['#0d6efd','#dc3545','#ffc107']
        }]
    },
    options: chartOptions
});

// Beneficiaries by Age Group
new Chart(document.getElementById('ageGroupChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($ageLabels) ?>,
        datasets: [{
            label: 'Beneficiaries',
            data: <?= json_encode($ageCounts) ?>,
            backgroundColor: '#198754'
        }]
    },
    options: chartOptions
});

// Reports per Month
new Chart(document.getElementById('reportMonthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [{
            label: 'Reports',
            data: <?= json_encode($monthCounts) ?>,
            borderColor: '#6610f2',
            backgroundColor: 'rgba(102,16,242,0.2)',
            fill: true,
            tension: 0.3
        }]
    },
    options: chartOptions
});

// Beneficiaries per Program
new Chart(document.getElementById('programChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($programLabels) ?>,
        datasets: [{
            label: 'Beneficiaries',
            data: <?= json_encode($programCounts) ?>,
            backgroundColor: '#fd7e14'
        }]
    },
    options: chartOptions
});
</script>
