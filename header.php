<?php

include __DIR__.'/alnaasDB.php';
$total_notifications = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE status='unread'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALNAAS Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* --- Global Styles --- */
body { background:#f4f6fa; font-family:'Poppins', sans-serif; color:#222; transition: background 0.4s, color 0.4s; }
.sidebar { height:100vh; width:250px; position:fixed; top:0; left:0; background:linear-gradient(180deg,#003087,#0059b3); color:white; padding-top:20px; transition:0.4s; }
.sidebar img.logo { width:100px; height:100px; object-fit:cover; border-radius:50%; margin-bottom:12px; }
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; border-radius:8px; margin:5px 10px; transition:0.3s; }
.sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); color:#fff; }
.main-content { margin-left:260px; padding:30px; }
.card { border:none; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); transition:0.3s; }
.dashboard-card { cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:140px; text-align:center; padding:25px; transition:0.3s; }
.dashboard-card:hover { transform: translateY(-6px) scale(1.03); box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.card-link { text-decoration:none; color:inherit; display:block; height:100%; }
.counter { font-size:2rem; font-weight:700; }

/* Topbar Buttons */
.topbar { position:fixed; top:15px; right:25px; display:flex; align-items:center; z-index:1000; }
.toggle-btn, .notif-btn { background:#fff; border:none; border-radius:50px; padding:8px 15px; margin-left:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); cursor:pointer; transition:all 0.3s; }
.toggle-btn:hover, .notif-btn:hover { background:#e9ecef; }
.notif-btn { position:relative; color:#daa520; text-decoration:none; }
.notif-btn i { font-size:1.3rem; }
.notif-btn .badge { position:absolute; top:-4px; right:-6px; font-size:0.7rem; padding:3px 6px; }

/* Dark Mode */
body.dark { background:#0e1117; color:#eee; }
.dark .sidebar { background:linear-gradient(180deg,#156eccff,#1b263b); }
.dark .sidebar a { color:#aaa; }
.dark .sidebar a:hover, .dark .sidebar a.active { background:rgba(46,14,14,0.1); color:#fff; }
.dark .card { background:#1e1e1e; color:#ddd; box-shadow:0 6px 20px rgba(255,255,255,0.05); }
.dark .toggle-btn, .dark .notif-btn { background:#1e1e1e; color:#ffd700; box-shadow:0 2px 10px rgba(255,255,255,0.05); }
.dark .toggle-btn:hover, .dark .notif-btn:hover { background:#2a2a2a; }
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <a href="notifications.php" class="notif-btn" title="View Notifications">
    <i class="bi bi-bell-fill"></i>
    <?php if($total_notifications>0): ?>
      <span class="badge bg-danger"><?= $total_notifications ?></span>
    <?php endif; ?>
  </a>
  <button id="themeToggle" class="toggle-btn"><i class="bi bi-moon-fill"></i> Dark Mode</button>
</div>

<!-- Sidebar -->
<div class="sidebar text-center">
  <img src="assets/PTPlogo.jpg" alt="ALNAAS Logo" class="logo">
  <h4 style="font-weight:700; font-size:1.4rem; letter-spacing:1px;">ALNAAS</h4>
  <a href="staffDashboard.php" class="active"><i class="bi bi-house"></i> Dashboard</a>
  <a href="beneficiaries.php"><i class="bi bi-people"></i> Beneficiaries</a>
  <a href="programs.php"><i class="bi bi-briefcase"></i> Programs</a>
  <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
  <form method="post" action="alnaasLogout.php">
      <button class="btn btn-danger m-3 w-75"><i class="bi bi-box-arrow-right"></i> Logout</button>
  </form>
</div>
