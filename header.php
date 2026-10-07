<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/alnaasDB.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: alnaasLogin.php");
    exit();
}

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en'; // Default language
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar', 'sw', 'fr'])) {
    $_SESSION['language'] = $_GET['lang'];
    // Redirect to remove lang parameter from URL
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

$current_lang = $_SESSION['language'];

$role = $_SESSION['role'];
$dashboard_link = match($role) {
    'Admin' => 'alnaasDash.php',
    'Staff' => 'staffDashboard.php',
    'Donor' => 'donorDashboard.php',
    default => 'alnaasLogin.php'
};

$total_notifications = $conn->query(
    "SELECT COUNT(*) AS c FROM notifications WHERE status='unread'"
)->fetch_assoc()['c'] ?? 0;
?>

<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALNAAS Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.11.1/css/flag-icons.min.css">

<style>
body {
    background:#f4f6fa;
    font-family:'Poppins', sans-serif;
    color:#222;
    transition: background 0.4s, color 0.4s;
}

.main-content {
    margin-left:260px;
    padding:30px;
    transition: margin-left 0.3s;
}

/* Sidebar */
.sidebar {
    height:100vh;
    width:250px;
    position:fixed;
    top:0;
    left:0;
    background:linear-gradient(180deg,#003087,#0059b3);
    color:white;
    padding-top:20px;
    transition:all 0.3s;
    overflow-y: auto;
    z-index: 1050;
}

.sidebar.hidden {
    left: -250px;
}

.sidebar img.logo {
    width:100px;
    height:100px;
    object-fit:cover;
    border-radius:50%;
    margin-bottom:12px;
}

.sidebar a {
    display:block;
    padding:12px 25px;
    color:#ddd;
    text-decoration:none;
    border-radius:8px;
    margin:5px 10px;
    transition:0.3s;
}

.sidebar a:hover,
.sidebar a.active {
    background:rgba(255,255,255,0.2);
    color:#fff;
}

/* Topbar */
.topbar {
    position:fixed;
    top:15px;
    right:25px;
    display:flex;
    align-items:center;
    gap: 10px;
    z-index:1000;
}

.hamburger-btn {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    background:#fff;
    border:none;
    border-radius:8px;
    padding:10px 15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    cursor:pointer;
    z-index: 1100;
    transition:all 0.3s;
}

.hamburger-btn:hover {
    background:#e9ecef;
}

.hamburger-btn i {
    font-size: 1.5rem;
    color: #003087;
}

.toggle-btn, .notif-btn, .lang-dropdown {
    background:#fff;
    border:none;
    border-radius:50px;
    padding:8px 15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    cursor:pointer;
    transition:all 0.3s;
}

.toggle-btn:hover,
.notif-btn:hover {
    background:#e9ecef;
}

.notif-btn {
    position:relative;
    color:#daa520;
}

.notif-btn .badge {
    position:absolute;
    top:-4px;
    right:-6px;
    font-size:0.7rem;
    padding:3px 6px;
}

/* Language Dropdown */
.lang-dropdown {
    position: relative;
    display: inline-block;
}

.lang-btn {
    background: transparent;
    border: none;
    padding: 8px 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #003087;
    transition: all 0.3s;
}

.lang-btn:hover {
    opacity: 0.8;
}

.lang-btn .fi {
    font-size: 1.2rem;
}

.lang-menu {
    display: none;
    position: absolute;
    top: 110%;
    right: 0;
    background: #fff;
    min-width: 180px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    padding: 8px 0;
    z-index: 1001;
}

.lang-dropdown:hover .lang-menu,
.lang-menu:hover {
    display: block;
}

.lang-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    color: #333;
    text-decoration: none;
    transition: all 0.3s;
}

.lang-menu a:hover {
    background: rgba(0, 48, 135, 0.1);
}

.lang-menu a.active {
    background: rgba(0, 48, 135, 0.15);
    font-weight: 600;
    color: #003087;
}

.lang-menu a .fi {
    font-size: 1.3rem;
}

/* Overlay for mobile */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
}

.sidebar-overlay.active {
    display: block;
}

/* DARK MODE */
body.dark {
    background:#0e1117;
    color:#eee;
}

.dark .sidebar {
    background:linear-gradient(180deg,#156eccff,#1b263b);
}

.dark .sidebar a {
    color:#aaa;
}

.dark .sidebar a:hover,
.dark .sidebar a.active {
    background:rgba(46,14,14,0.1);
    color:#fff;
}

.dark .toggle-btn,
.dark .notif-btn,
.dark .hamburger-btn,
.dark .lang-dropdown {
    background:#1e1e1e;
    color:#ffd700;
    box-shadow:0 2px 10px rgba(255,255,255,0.05);
}

.dark .toggle-btn:hover,
.dark .notif-btn:hover,
.dark .hamburger-btn:hover {
    background:#2a2a2a;
}

.dark .hamburger-btn i {
    color: #ffd700;
}

.dark .lang-btn {
    color: #ffd700;
}

.dark .lang-menu {
    background: #1e1e1e;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.dark .lang-menu a {
    color: #ddd;
}

.dark .lang-menu a:hover {
    background: rgba(255, 215, 0, 0.1);
}

.dark .lang-menu a.active {
    background: rgba(255, 215, 0, 0.15);
    color: #ffd700;
}

/* Sidebar scrollbar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .hamburger-btn {
        display: block;
    }

    .sidebar {
        left: -250px;
    }

    .sidebar.active {
        left: 0;
    }

    .main-content {
        margin-left: 0;
        padding: 80px 15px 30px 15px;
    }

    .topbar {
        top: 15px;
        right: 15px;
    }

    .toggle-btn span,
    .notif-btn span {
        display: none;
    }

    .toggle-btn,
    .notif-btn {
        padding: 8px 12px;
    }

    .lang-btn span {
        display: none;
    }

    .lang-btn {
        padding: 8px 12px;
    }
}

@media (max-width: 480px) {
    .main-content {
        padding: 70px 10px 20px 10px;
    }

    .sidebar {
        width: 220px;
    }

    .sidebar.hidden {
        left: -220px;
    }

    .topbar {
        gap: 5px;
    }
}
</style>
</head>
<body>

<!-- Hamburger Menu Button (Mobile) -->
<button id="hamburgerBtn" class="hamburger-btn">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="topbar">
    <!-- Language Selector -->
    <div class="lang-dropdown">
        <button class="lang-btn">
            <?php
            $flags = ['en' => 'fi-gb', 'ar' => 'fi-sa', 'sw' => 'fi-tz', 'fr' => 'fi-fr'];
            $lang_names = ['en' => 'EN', 'ar' => 'AR', 'sw' => 'SW', 'fr' => 'FR'];
            ?>
            <span class="fi <?= $flags[$current_lang] ?>"></span>
            <span><?= $lang_names[$current_lang] ?></span>
            <i class="bi bi-chevron-down" style="font-size: 0.8rem;"></i>
        </button>
        <div class="lang-menu">
            <a href="?lang=en" class="<?= $current_lang == 'en' ? 'active' : '' ?>">
                <span class="fi fi-gb"></span>
                <span>English</span>
            </a>
            <a href="?lang=ar" class="<?= $current_lang == 'ar' ? 'active' : '' ?>">
                <span class="fi fi-sa"></span>
                <span>العربية</span>
            </a>
            <a href="?lang=sw" class="<?= $current_lang == 'sw' ? 'active' : '' ?>">
                <span class="fi fi-tz"></span>
                <span>Kiswahili</span>
            </a>
            <a href="?lang=fr" class="<?= $current_lang == 'fr' ? 'active' : '' ?>">
                <span class="fi fi-fr"></span>
                <span>Français</span>
            </a>
        </div>
    </div>

    <!-- Notifications -->
    <a href="notifications.php" class="notif-btn" title="View Notifications">
        <i class="bi bi-bell-fill"></i>
        <?php if($total_notifications > 0): ?>
            <span class="badge bg-danger"><?= $total_notifications ?></span>
        <?php endif; ?>
    </a>

    <!-- Dark Mode Switch -->
    <button id="themeToggle" class="toggle-btn">
        <i class="bi bi-moon-fill"></i> <span>Dark Mode</span>
    </button>
</div>

<!-- SIDEBAR -->
<div id="sidebar" class="sidebar text-center">
    <img src="assets/PTPlogo.jpg" alt="ALNAAS Logo" class="logo">
    <h4 style="font-weight:700; font-size:1.4rem; letter-spacing:1px;">ALNAAS</h4>

    <a href="<?= $dashboard_link ?>"
        class="<?= basename($_SERVER['PHP_SELF']) == basename($dashboard_link) ? 'active' : '' ?>">
        <i class="bi bi-house"></i> Dashboard
    </a>

    <a href="beneficiaries.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'beneficiaries.php' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Beneficiaries
    </a>

    <a href="programs.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'programs.php' ? 'active' : '' ?>">
        <i class="bi bi-briefcase"></i> Programs
    </a>

    <a href="enrollments.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : '' ?>">
        <i class="bi bi-person-check-fill"></i> Enrollments
    </a>

    <a href="feedback.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : '' ?>">
        <i class="bi bi-chat-left-text"></i> Feedback
    </a>

    <a href="reports.php"
        class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart"></i> Reports
    </a>

    <form method="post" action="alnaasLogout.php">
        <button class="btn btn-danger m-3 w-75">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </form>
</div>

<script>
// Hamburger Menu Toggle
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

hamburgerBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
});

// Close sidebar when clicking overlay
sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
});

// Close sidebar when clicking a link (mobile)
if (window.innerWidth <= 768) {
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    });
}

// Dark Mode Theme
const toggleBtn = document.getElementById('themeToggle');
const body = document.body;

// Load saved theme
if(localStorage.getItem('theme') === 'dark'){
    body.classList.add('dark');
    toggleBtn.innerHTML = '<i class="bi bi-brightness-high-fill"></i> <span>Light Mode</span>';
}

// Toggle dark mode
toggleBtn.addEventListener('click', () => {
    body.classList.toggle('dark');
    const isDark = body.classList.contains('dark');
    toggleBtn.innerHTML = isDark
        ? '<i class="bi bi-brightness-high-fill"></i> <span>Light Mode</span>'
        : '<i class="bi bi-moon-fill"></i> <span>Dark Mode</span>';
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
});
</script>