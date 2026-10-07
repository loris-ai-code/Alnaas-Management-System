<?php

use Dompdf\Dompdf;

session_start();
include 'alnaasDB.php'; 

if (!isset($_SESSION['username'])) {
    header("Location: alnaasLogin.php");
    exit();
}

$role = $_SESSION['role'] ?? 'Staff';
$uploadDir = "uploads/reports/";
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

$filter_program = isset($_GET['program_id']) && $_GET['program_id'] !== '' ? intval($_GET['program_id']) : null;
$filter_user = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;
$filter_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$filter_to   = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$filter_q    = isset($_GET['q']) ? trim($_GET['q']) : null;


if (isset($_GET['export'])) {

    require_once __DIR__ . '/vendor/autoload.php';
    $type = $_GET['export']; 
    $singleID = isset($_GET['id']) && $_GET['id'] !== '' ? intval($_GET['id']) : null;

    // Build base query + params according to filters or single id
    $params = [];
    $types = '';

    if ($singleID) {
        $sql = "SELECT r.*, p.name AS program, u.name AS user
                FROM reports r
                LEFT JOIN programs p ON r.program_id = p.id
                LEFT JOIN users u ON r.user_id = u.ptpID
                WHERE r.id = ?";
        $types .= 'i';
        $params[] = $singleID;
    } else {
        $sql = "SELECT r.*, p.name AS program, u.name AS user
                FROM reports r
                LEFT JOIN programs p ON r.program_id = p.id
                LEFT JOIN users u ON r.user_id = u.ptpID
                WHERE 1=1";

        if ($filter_program) { $sql .= " AND r.program_id = ?"; $types .= 'i'; $params[] = $filter_program; }
        if ($filter_user)    { $sql .= " AND r.user_id = ?";    $types .= 'i'; $params[] = $filter_user; }
        if ($filter_from)    { $sql .= " AND r.report_date >= ?"; $types .= 's'; $params[] = $filter_from; }
        if ($filter_to)      { $sql .= " AND r.report_date <= ?"; $types .= 's'; $params[] = $filter_to; }
        if ($filter_q) {
            $sql .= " AND (r.title LIKE ? OR r.content LIKE ?)";
            $types .= 'ss';
            $params[] = "%{$filter_q}%";
            $params[] = "%{$filter_q}%";
        }

        $sql .= " ORDER BY r.report_date ASC";
    }

    // Prepare and execute
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $bind_names[] = $types;
        for ($i=0; $i<count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // If exporting Excel
    if ($type === 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=ALNAAS_Reports_" . date('Ymd_His') . ".xls");
        echo "ID\tProgram\tUser\tTitle\tDate\tAttachment\tContent\n";
        foreach ($data as $r) {
            $content = str_replace(["\r\n", "\n", "\t"], [" ", " ", " "], $r['content']);
            $attachment = $r['attachment'] ?? '';
            echo "{$r['id']}\t{$r['program']}\t{$r['user']}\t{$r['title']}\t{$r['report_date']}\t{$attachment}\t{$content}\n";
        }
        exit();
    }

    // If exporting PDF (portrait) with cover & summary
    if ($type === 'pdf') {
        $dompdf = new Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->set_option('isHtml5ParserEnabled', true);

        // Prepare summary statistics
        if ($singleID) {
            $totalReports = count($data);
            $reportsThisMonth = 0;
            $reportsWithAttachments = ($data[0]['attachment'] ? 1 : 0);
            $programCount = $data[0]['program'] ? 1 : 0;
            $topStaff = [[ 'name' => $data[0]['user'], 'count' => 1 ]];
        } else {
            // Build aggregate query with same filters
            $aggSql = "SELECT 
                        COUNT(*) AS total,
                        SUM(CASE WHEN attachment IS NOT NULL AND attachment <> '' THEN 1 ELSE 0 END) AS with_attachments
                        FROM reports r WHERE 1=1";
            $params2 = [];
            $types2 = '';
            if ($filter_program) { $aggSql .= " AND r.program_id = ?"; $types2 .= 'i'; $params2[] = $filter_program; }
            if ($filter_user)    { $aggSql .= " AND r.user_id = ?";    $types2 .= 'i'; $params2[] = $filter_user; }
            if ($filter_from)    { $aggSql .= " AND r.report_date >= ?"; $types2 .= 's'; $params2[] = $filter_from; }
            if ($filter_to)      { $aggSql .= " AND r.report_date <= ?"; $types2 .= 's'; $params2[] = $filter_to; }
            if ($filter_q)       { $aggSql .= " AND (r.title LIKE ? OR r.content LIKE ?)"; $types2 .= 'ss'; $params2[] = "%{$filter_q}%"; $params2[] = "%{$filter_q}%"; }

            $stmt2 = $conn->prepare($aggSql);
            if ($types2 !== '') {
                $bind_names2[] = $types2;
                for ($i=0;$i<count($params2);$i++) {
                    $bindName = 'b2'.$i;
                    $$bindName = $params2[$i];
                    $bind_names2[] = &$$bindName;
                }
                call_user_func_array([$stmt2, 'bind_param'], $bind_names2);
            }
            $stmt2->execute();
            $aggRes = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            $totalReports = intval($aggRes['total']);
            $reportsWithAttachments = intval($aggRes['with_attachments']);

            // Reports this month
            $thisMonthStart = date('Y-m-01');
            $thisMonthEnd = date('Y-m-t');
            $stmt3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM reports r WHERE r.report_date BETWEEN ? AND ?");
            $stmt3->bind_param('ss', $thisMonthStart, $thisMonthEnd);
            $stmt3->execute();
            $reportsThisMonth = intval($stmt3->get_result()->fetch_assoc()['cnt']);
            $stmt3->close();

            // Program count
            $progSql = "SELECT COUNT(DISTINCT r.program_id) AS pcount FROM reports r WHERE 1=1";
            $params3 = []; $types3 = '';
            if ($filter_program) { $progSql .= " AND r.program_id = ?"; $types3 .= 'i'; $params3[] = $filter_program; }
            if ($filter_user)    { $progSql .= " AND r.user_id = ?";    $types3 .= 'i'; $params3[] = $filter_user; }
            if ($filter_from)    { $progSql .= " AND r.report_date >= ?"; $types3 .= 's'; $params3[] = $filter_from; }
            if ($filter_to)      { $progSql .= " AND r.report_date <= ?"; $types3 .= 's'; $params3[] = $filter_to; }
            if ($filter_q)       { $progSql .= " AND (r.title LIKE ? OR r.content LIKE ?)"; $types3 .= 'ss'; $params3[] = "%{$filter_q}%"; $params3[] = "%{$filter_q}%"; }

            $stmt4 = $conn->prepare($progSql);
            if ($types3 !== '') {
                $bind_names3[] = $types3;
                for ($i=0;$i<count($params3);$i++) {
                    $bn = 'b3'.$i; $$bn = $params3[$i]; $bind_names3[] = &$$bn;
                }
                call_user_func_array([$stmt4,'bind_param'],$bind_names3);
            }
            $stmt4->execute();
            $programCount = intval($stmt4->get_result()->fetch_assoc()['pcount']);
            $stmt4->close();

            // Top 3 staff
            $topSql = "SELECT u.name AS name, COUNT(*) AS cnt
                       FROM reports r
                       LEFT JOIN users u ON r.user_id = u.ptpID
                       WHERE 1=1";
            $params4 = []; $types4 = '';
            if ($filter_program) { $topSql .= " AND r.program_id = ?"; $types4 .= 'i'; $params4[] = $filter_program; }
            if ($filter_user)    { $topSql .= " AND r.user_id = ?";    $types4 .= 'i'; $params4[] = $filter_user; }
            if ($filter_from)    { $topSql .= " AND r.report_date >= ?"; $types4 .= 's'; $params4[] = $filter_from; }
            if ($filter_to)      { $topSql .= " AND r.report_date <= ?"; $types4 .= 's'; $params4[] = $filter_to; }
            if ($filter_q)       { $topSql .= " AND (r.title LIKE ? OR r.content LIKE ?)"; $types4 .= 'ss'; $params4[] = "%{$filter_q}%"; $params4[] = "%{$filter_q}%"; }

            $topSql .= " GROUP BY r.user_id ORDER BY cnt DESC LIMIT 3";
            $stmt5 = $conn->prepare($topSql);
            if ($types4 !== '') {
                $bind_names4[] = $types4;
                for ($i=0;$i<count($params4);$i++) {
                    $bn2 = 'b4'.$i; $$bn2 = $params4[$i]; $bind_names4[] = &$$bn2;
                }
                call_user_func_array([$stmt5,'bind_param'],$bind_names4);
            }
            $stmt5->execute();
            $topStaff = $stmt5->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt5->close();
        }

        // Logo support
        $logoPath = __DIR__ . '/assets/PTPlogo.jpg';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        // Build PDF HTML
        ob_start();
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: DejaVu Sans, sans-serif; color:#222; margin:30px; font-size:12px; }
                .cover { text-align:center; padding-top:20px; }
                .logo { max-width:120px; margin-bottom:10px; }
                .title { color:#003087; font-size:20px; margin:6px 0; }
                .meta { font-size:11px; color:#444; margin-bottom:18px; }
                .summary { margin:18px 0; padding:12px; border:1px solid #e0e0e0; border-radius:8px; background:#f7f9fc; }
                .summary .row { display:flex; gap:12px; flex-wrap:wrap; }
                .stat { flex:1 1 180px; background:#fff; border-radius:6px; padding:10px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
                .stat h3 { margin:0; color:#003087; font-size:16px; }
                table { width:100%; border-collapse:collapse; margin-top:12px; font-size:11px; }
                th, td { border:1px solid #ddd; padding:8px; vertical-align:top; }
                th { background:#003087; color:#fff; }
                .footer { text-align:center; font-size:10px; color:#666; margin-top:12px; }
                .cover-sub { margin-top:8px; font-size:12px; color:#333; }
            </style>
        </head>
        <body>
        <div class="cover">
            <?php if ($logoBase64): ?>
                <img src="<?php echo $logoBase64; ?>" class="logo" />
            <?php endif; ?>
            <div class="title">ALNAAS MANAGEMENT SYSTEM</div>
            <div class="cover-sub"><strong>Report Summary</strong></div>
            <div class="meta">Prepared by: <?php echo htmlspecialchars($_SESSION['username']); ?> &nbsp; | &nbsp; Generated on: <?php echo date('F j, Y, g:i A'); ?></div>
        </div>

        <div class="summary">
            <div class="row">
                <div class="stat">
                    <h3><?php echo intval($totalReports ?? count($data)); ?></h3>
                    <div>Total Reports</div>
                </div>
                <div class="stat">
                    <h3><?php echo intval($programCount ?? 0); ?></h3>
                    <div>Programs with Reports</div>
                </div>
                <div class="stat">
                    <h3><?php echo intval($reportsThisMonth ?? 0); ?></h3>
                    <div>Reports This Month</div>
                </div>
                <div class="stat">
                    <h3><?php echo intval($reportsWithAttachments ?? 0); ?></h3>
                    <div>Reports with Attachments</div>
                </div>
            </div>

            <?php if (!empty($topStaff)): ?>
                <div style="margin-top:10px;">
                    <strong>Top Active Staff</strong>
                    <ol>
                        <?php foreach ($topStaff as $s): ?>
                            <li><?php echo htmlspecialchars($s['name'] ?? 'Unknown'); ?> — <?php echo intval($s['cnt']); ?> report(s)</li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <table>
                <thead>
                    <tr>
                        <th style="width:5%;">ID</th>
                        <th style="width:12%;">Program</th>
                        <th style="width:12%;">User</th>
                        <th style="width:22%;">Title</th>
                        <th style="width:10%;">Date</th>
                        <th style="width:12%;">Attachment</th>
                        <th>Content</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['program']); ?></td>
                            <td><?php echo htmlspecialchars($r['user']); ?></td>
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td><?php echo $r['report_date']; ?></td>
                            <td><?php echo $r['attachment'] ? htmlspecialchars($r['attachment']) : 'None'; ?></td>
                            <td><?php echo nl2br(htmlspecialchars($r['content'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">ALNAAS Management System • Generated <?php echo date('F j, Y'); ?></div>

        </body>
        </html>
        <?php
        $html = ob_get_clean();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $fileName = ($singleID ? "ALNAAS_Report_{$singleID}_" : "ALNAAS_Reports_Filtered_") . date('Ymd_His') . ".pdf";
        $dompdf->stream($fileName, ["Attachment" => true]);
        exit();
    }
}

// ------------------------------------------
// 2) CRUD Logic (Add / Update / Delete)
// ------------------------------------------

// ADD
if (isset($_POST['add_report']) && ($role == 'Admin' || $role == 'Staff')) {
    $fileName = "";
    if (!empty($_FILES['attachment']['name'])) {
        $fileName = time() . "_" . basename($_FILES['attachment']['name']);
        move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName);
    }
    $stmt = $conn->prepare("INSERT INTO reports (program_id, user_id, title, content, report_date, attachment) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iissss", $_POST['program_id'], $_SESSION['ptpID'], $_POST['title'], $_POST['content'], $_POST['report_date'], $fileName);
    $stmt->execute(); $stmt->close();
    header("Location: reports.php");
    exit();
}

// UPDATE
if (isset($_POST['update_report']) && ($role == 'Admin' || $role == 'Staff')) {
    $fileName = $_POST['existing_attachment'] ?? '';
    if (!empty($_FILES['attachment']['name'])) {
        $fileName = time() . "_" . basename($_FILES['attachment']['name']);
        move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName);
    }
    $stmt = $conn->prepare("UPDATE reports SET program_id=?, title=?, content=?, report_date=?, attachment=? WHERE id=?");
    $stmt->bind_param("issssi", $_POST['program_id'], $_POST['title'], $_POST['content'], $_POST['report_date'], $fileName, $_POST['id']);
    $stmt->execute(); $stmt->close();
    header("Location: reports.php");
    exit();
}

// DELETE
if (isset($_GET['delete']) && $role == 'Admin') {
    $id = intval($_GET['delete']);
    $q = $conn->prepare("SELECT attachment FROM reports WHERE id=?"); $q->bind_param('i',$id); $q->execute();
    $res = $q->get_result()->fetch_assoc(); $q->close();
    if ($res && $res['attachment']) @unlink($uploadDir . $res['attachment']);
    $stmt = $conn->prepare("DELETE FROM reports WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    header("Location: reports.php"); exit();
}

// ------------------------------------------
// 3) Fetch UI data (programs, users, reports for table - filtered)
// ------------------------------------------
$programs = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");
$users = $conn->query("SELECT ptpID, name FROM users WHERE role IN ('Staff','Admin') ORDER BY name ASC");

// Build filtered list for display
$displayParams = []; $displayTypes = '';
$displaySql = "SELECT r.*, p.name AS program_name, u.name AS user_name
               FROM reports r
               LEFT JOIN programs p ON r.program_id = p.id
               LEFT JOIN users u ON r.user_id = u.ptpID
               WHERE 1=1";

if ($filter_program) { $displaySql .= " AND r.program_id = ?"; $displayTypes .= 'i'; $displayParams[] = $filter_program; }
if ($filter_user)    { $displaySql .= " AND r.user_id = ?";    $displayTypes .= 'i'; $displayParams[] = $filter_user; }
if ($filter_from)    { $displaySql .= " AND r.report_date >= ?"; $displayTypes .= 's'; $displayParams[] = $filter_from; }
if ($filter_to)      { $displaySql .= " AND r.report_date <= ?"; $displayTypes .= 's'; $displayParams[] = $filter_to; }
if ($filter_q)       { $displaySql .= " AND (r.title LIKE ? OR r.content LIKE ?)"; $displayTypes .= 'ss'; $displayParams[] = "%{$filter_q}%"; $displayParams[] = "%{$filter_q}%"; }

$displaySql .= " ORDER BY r.report_date DESC";
$displayStmt = $conn->prepare($displaySql);
if ($displayTypes !== '') {
    $bindArr = [$displayTypes];
    for ($i=0;$i<count($displayParams);$i++) { $bindArr[] = &$displayParams[$i]; }
    call_user_func_array([$displayStmt, 'bind_param'], $bindArr);
}
$displayStmt->execute();
$reports = $displayStmt->get_result();

// ------------------------------------------
// 4) Render page
// ------------------------------------------
include 'header.php';


?>
<style>
    body { background:#f4f6fa; }
    .main-content { padding: 20px; margin-top: 70px; }
    .page-header { 
        background: linear-gradient(135deg, #3e5ce0ff 0%, #3d26d6ff 100%);
        color: white;
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .page-header h3 { margin: 0; font-weight: 600; }
    .card { 
        border-radius:12px; 
        box-shadow:0 2px 8px rgba(0,0,0,0.08); 
        border: none;
        margin-bottom: 20px;
    }
    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    .filter-row .form-control, .filter-row .form-select { 
        height:42px;
        border: 1px solid #e0e0e0;
    }
    .action-buttons { 
        display:flex; 
        gap:6px; 
        justify-content:center; 
        align-items:center; 
        flex-wrap: wrap;
    }
    .table thead th { 
        position: sticky; 
        top: 0; 
        z-index: 1;
        background: #415cd3ff;
        color: white;
        border: none;
    }
    .small-muted { font-size:13px; color:#666; font-weight: 500; }
    .stats-row { margin-bottom: 25px; }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    .stat-card h4 {
        color: #667eea;
        font-size: 32px;
        font-weight: 700;
        margin: 10px 0;
    }
    .stat-card .label {
        color: #666;
        font-size: 13px;
        font-weight: 500;
    }
    .btn-export {
        background: #667eea;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    .btn-export:hover {
        background: #5568d3;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    }
    .table-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        overflow: hidden;
    }
    .table-responsive {
        max-height: 600px;
        overflow-y: auto;
    }
    .btn-action {
        padding: 6px 12px;
        font-size: 13px;
        border-radius: 6px;
    }
    
    /* File upload styling */
    .form-control[type="file"] {
        padding: 10px;
        border: 2px dashed #e0e0e0;
        background: #f8f9fa;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .form-control[type="file"]:hover {
        border-color: #667eea;
        background: #f0f4ff;
    }
    
    .file-upload-area {
        border: 2px dashed #d0d0d0;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        background: #f8f9fa;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .file-upload-area:hover {
        border-color: #667eea;
        background: #f0f4ff;
    }
    
    .file-upload-area i {
        font-size: 32px;
        color: #667eea;
        margin-bottom: 10px;
    }
    
    .file-selected {
        display: none;
        background: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 6px;
        padding: 10px;
        margin-top: 10px;
    }
    
    .file-selected.show {
        display: block;
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h3></i> Reports Management</h3>
        <p style="margin: 8px 0 0 0; opacity: 0.95; font-size: 14px;">View, filter, and export activity reports</p>
    </div>

    <!-- Filters Section -->
    <div class="filter-section">
        <form method="GET" class="row gy-2 gx-3 align-items-end filter-row">
            <div class="col-md-3">
                <label class="form-label small-muted">Program</label>
                <select name="program_id" class="form-select">
                    <option value="">All Programs</option>
                    <?php while($p = $programs->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>" <?= ($filter_program == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small-muted">User (Staff)</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <option value="<?= $u['ptpID'] ?>" <?= ($filter_user == $u['ptpID']) ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small-muted">From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_from) ?>" class="form-control">
            </div>

            <div class="col-md-2">
                <label class="form-label small-muted">To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_to) ?>" class="form-control">
            </div>

            <div class="col-md-2">
                <label class="form-label small-muted">Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>" class="form-control" placeholder="Keywords...">
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" style="height: 42px;"><i class="bi bi-funnel"></i></button>
                <a href="reports.php" class="btn btn-secondary" style="height: 42px;"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Add Report Form (if allowed) -->
    <?php if ($role != 'Donor'): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-plus-circle"></i> Add New Report
            </h5>
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small-muted">
                        <i class="bi bi-calendar-event"></i> Program
                    </label>
                    <select name="program_id" class="form-select" required>
                        <option value="">Select Program</option>
                        <?php
                        $pQ = $conn->query("SELECT id,name FROM programs ORDER BY name ASC");
                        while($pp = $pQ->fetch_assoc()):
                        ?>
                            <option value="<?= $pp['id'] ?>"><?= htmlspecialchars($pp['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small-muted">
                        <i class="bi bi-file-text"></i> Report Title
                    </label>
                    <input type="text" name="title" class="form-control" placeholder="Enter title" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small-muted">
                        <i class="bi bi-calendar3"></i> Date
                    </label>
                    <input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small-muted">
                        <i class="bi bi-paperclip"></i> Attach File (Optional)
                    </label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" id="fileInput">
                    <small class="text-muted">Supported: PDF, Images, Word docs (Max 10MB)</small>
                </div>
                <div class="col-12">
                    <label class="form-label small-muted">
                        <i class="bi bi-text-paragraph"></i> Report Content
                    </label>
                    <textarea name="content" class="form-control" rows="3" placeholder="Enter detailed report content here..." required></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="add_report" class="btn btn-success"><i class="bi bi-plus-lg"></i> Add Report</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Summary -->
    <div class="row stats-row">
        <div class="col-md-3">
            <div class="stat-card">
                <?php
                $cntRow = $conn->query("SELECT COUNT(*) AS c FROM reports r WHERE 1=1" . 
                    ($filter_program ? " AND r.program_id = {$filter_program}" : "") .
                    ($filter_user ? " AND r.user_id = {$filter_user}" : "") .
                    ($filter_from ? " AND r.report_date >= '{$filter_from}'" : "") .
                    ($filter_to ? " AND r.report_date <= '{$filter_to}'" : "") .
                    ($filter_q ? " AND (r.title LIKE '%{$conn->real_escape_string($filter_q)}%' OR r.content LIKE '%{$conn->real_escape_string($filter_q)}%')" : "")
                )->fetch_assoc();
                ?>
                <div class="label">Total Reports</div>
                <h4><?= intval($cntRow['c']) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <?php
                $thisMonthStart = date('Y-m-01'); 
                $thisMonthEnd = date('Y-m-t');
                $rm = $conn->prepare("SELECT COUNT(*) AS c FROM reports WHERE report_date BETWEEN ? AND ?");
                $rm->bind_param('ss',$thisMonthStart,$thisMonthEnd); 
                $rm->execute();
                $rmc = $rm->get_result()->fetch_assoc(); 
                $rm->close();
                ?>
                <div class="label">This Month</div>
                <h4><?= intval($rmc['c']) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <?php
                $waq = $conn->query("SELECT COUNT(*) AS c FROM reports WHERE attachment IS NOT NULL AND attachment <> ''")->fetch_assoc();
                ?>
                <div class="label">With Attachments</div>
                <h4><?= intval($waq['c']) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <?php
                $pc = $conn->query("SELECT COUNT(DISTINCT program_id) AS c FROM reports")->fetch_assoc();
                ?>
                <div class="label">Programs</div>
                <h4><?= intval($pc['c']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-table"></i> Reports List</h5>
            <div class="d-flex gap-2">
                <?php if ($role != 'Donor'): ?>
                    <a href="?export=pdf<?= $filter_program ? '&program_id='.$filter_program : '' ?><?= $filter_user ? '&user_id='.$filter_user : '' ?><?= $filter_from ? '&date_from='.$filter_from : '' ?><?= $filter_to ? '&date_to='.$filter_to : '' ?><?= $filter_q ? '&q='.$filter_q : '' ?>" class="btn btn-export btn-sm">
                        <i class="bi bi-file-pdf"></i> Export PDF
                    </a>
                    <a href="?export=excel<?= $filter_program ? '&program_id='.$filter_program : '' ?><?= $filter_user ? '&user_id='.$filter_user : '' ?><?= $filter_from ? '&date_from='.$filter_from : '' ?><?= $filter_to ? '&date_to='.$filter_to : '' ?><?= $filter_q ? '&q='.$filter_q : '' ?>" class="btn btn-export btn-sm" style="background: #28a745;">
                        <i class="bi bi-file-excel"></i> Export Excel
                    </a>
                <?php else: ?>
                    <a href="?export=pdf<?= $filter_program ? '&program_id='.$filter_program : '' ?><?= $filter_user ? '&user_id='.$filter_user : '' ?><?= $filter_from ? '&date_from='.$filter_from : '' ?><?= $filter_to ? '&date_to='.$filter_to : '' ?><?= $filter_q ? '&q='.$filter_q : '' ?>" class="btn btn-export btn-sm">
                        <i class="bi bi-file-pdf"></i> Export PDF
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 12%;">Program</th>
                        <th style="width: 12%;">User</th>
                        <th style="width: 20%;">Title</th>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 8%;">File</th>
                        <th style="width: 25%;">Content</th>
                        <th style="width: 8%;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $reports->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $row['id'] ?></strong></td>
                            <td><?= htmlspecialchars($row['program_name']) ?></td>
                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['report_date'])) ?></td>
                            <td>
                                <?php if ($row['attachment']): ?>
                                    <a href="<?= $uploadDir . htmlspecialchars($row['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-action">
                                        <i class="bi bi-paperclip"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars(substr($row['content'],0,100)) ?><?= strlen($row['content'])>100 ? '...' : '' ?></small>
                            </td>
                            <td class="text-center">
                                <div class="action-buttons">
                                    <?php if ($role != 'Donor'): ?>
                                        <button class="btn btn-info btn-sm text-white btn-action" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($role == 'Admin'): ?>
                                            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this report?')" class="btn btn-danger btn-sm btn-action">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a href="?export=pdf&id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm btn-action" title="Export to PDF">
                                        <i class="bi bi-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header" style="background: linear-gradient(135deg, #3c58d3ff 0%, #2c3cd0ff 100%); color: white;">
                                        <h5 class="modal-title">
                                            <i class="bi bi-pencil-square"></i> Edit Report #<?= $row['id'] ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($row['attachment']) ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label small-muted">Program</label>
                                                <select name="program_id" class="form-select" required>
                                                    <?php
                                                    $pgList = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");
                                                    while($pg = $pgList->fetch_assoc()):
                                                    ?>
                                                        <option value="<?= $pg['id'] ?>" <?= ($pg['id']==$row['program_id'])?'selected':'' ?>>
                                                            <?= htmlspecialchars($pg['name']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small-muted">Title</label>
                                                    <input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small-muted">Date</label>
                                                    <input type="date" name="report_date" value="<?= $row['report_date'] ?>" class="form-control" required>
                                                </div>
                                            </div>

                                            <div class="mb-3 mt-3">
                                                <label class="form-label small-muted">
                                                    <i class="bi bi-paperclip"></i> Attachment
                                                </label>
                                                <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                <?php if($row['attachment']): ?>
                                                    <div class="alert alert-info mt-2 mb-0" style="padding: 8px 12px;">
                                                        <i class="bi bi-file-earmark-check"></i> 
                                                        Current file: <strong><?= htmlspecialchars($row['attachment']) ?></strong>
                                                        <br>
                                                        <small class="text-muted">Upload a new file to replace it</small>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted d-block mt-1">
                                                    Supported formats: PDF, Images (JPG, PNG), Word documents (Max 10MB)
                                                </small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small-muted">Content</label>
                                                <textarea name="content" class="form-control" rows="5"><?= htmlspecialchars($row['content']) ?></textarea>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_report" class="btn btn-primary">
                                                <i class="bi bi-check-lg"></i> Save Changes
                                            </button>
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

<script>
// File upload preview and validation
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Check file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            alert('File size must be less than 10MB');
            e.target.value = '';
            return;
        }
        
        // Show file name
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        console.log(`Selected: ${fileName} (${fileSize}MB)`);
    }
});

// Edit modal file upload handlers
document.querySelectorAll('.modal input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                e.target.value = '';
                return;
            }
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>