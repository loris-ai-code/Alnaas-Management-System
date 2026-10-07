<?php
// feedback.php - Complete Feedback Management System
require_once __DIR__ . '/header.php'; // starts session & DB

// Quick helper function
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

$role = $_SESSION['role'] ?? 'Guest';
$staff_id = $_SESSION['ptpID'] ?? null;
$alert = null;

// ----------------------
// AUTO-DETECT COLUMN NAMES (Makes it work with any database structure)
// ----------------------
$programCol = 'name';
$checkProg = $conn->query("SHOW COLUMNS FROM programs WHERE Field IN ('name', 'program_name')");
if ($checkProg && $checkProg->num_rows > 0) {
    $col = $checkProg->fetch_assoc();
    $programCol = $col['Field'];
}

$userCol = 'username';
$checkUser = $conn->query("SHOW COLUMNS FROM users WHERE Field IN ('name', 'fullname', 'username')");
if ($checkUser && $checkUser->num_rows > 0) {
    while ($col = $checkUser->fetch_assoc()) {
        if (in_array($col['Field'], ['name', 'fullname'])) {
            $userCol = $col['Field'];
            break;
        }
    }
}

// ----------------------
// HANDLE FORM SUBMISSIONS
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // APPROVE / REJECT Feedback
    if (isset($_POST['action']) && isset($_POST['fid']) && in_array($_POST['action'], ['approve','reject'])) {
        if (!in_array($role, ['Admin','Staff'])) {
            $alert = ['type'=>'danger','text'=>'Permission denied.'];
        } else {
            $fid = intval($_POST['fid']);
            $newStatus = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $fid);
            if ($stmt->execute()) {
                $alert = ['type'=>'success','text'=>"Feedback #$fid marked as $newStatus!"];
            } else {
                $alert = ['type'=>'danger','text'=>'Database error: ' . $stmt->error];
            }
            $stmt->close();
        }
    }

    // DELETE Feedback
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['fid'])) {
        if ($role !== 'Admin') {
            $alert = ['type'=>'danger','text'=>'Only Admin can delete feedback.'];
        } else {
            $fid = intval($_POST['fid']);
            $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
            $stmt->bind_param("i", $fid);
            if ($stmt->execute()) {
                $alert = ['type'=>'success','text'=>"Feedback #$fid deleted successfully!"];
            } else {
                $alert = ['type'=>'danger','text'=>'Database error: ' . $stmt->error];
            }
            $stmt->close();
        }
    }

    // ADD NEW Feedback
    if (isset($_POST['submit_feedback'])) {
        $beneficiary_id = intval($_POST['beneficiary_id'] ?? 0);
        $program_id_raw = $_POST['program_id'] ?? '';
        $program_id = ($program_id_raw === '' || $program_id_raw === '0') ? null : intval($program_id_raw);
        $feedback_text = trim($_POST['feedback_text'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);
        $anonymous = isset($_POST['anonymous']) ? 1 : 0;
        
        // Validation
        if ($beneficiary_id <= 0) {
            $alert = ['type'=>'danger','text'=>'Please select a beneficiary.'];
        } elseif ($feedback_text === '') {
            $alert = ['type'=>'danger','text'=>'Please enter feedback text.'];
        } elseif ($rating < 1 || $rating > 5) {
            $alert = ['type'=>'danger','text'=>'Please select a rating between 1-5.'];
        } else {
            // Insert feedback
            if ($program_id === null) {
                $stmt = $conn->prepare("INSERT INTO feedback (beneficiary_id, program_id, feedback_text, rating, anonymous, staff_id, created_at) VALUES (?, NULL, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isiii", $beneficiary_id, $feedback_text, $rating, $anonymous, $staff_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO feedback (beneficiary_id, program_id, feedback_text, rating, anonymous, staff_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iisiii", $beneficiary_id, $program_id, $feedback_text, $rating, $anonymous, $staff_id);
            }
            
            if ($stmt->execute()) {
                $alert = ['type'=>'success','text'=>'Feedback submitted successfully! (Status: Pending approval)'];
            } else {
                $alert = ['type'=>'danger','text'=>'Error submitting feedback: ' . $stmt->error];
            }
            $stmt->close();
        }
    }
}

// ----------------------
// FETCH DATA FOR DISPLAY
// ----------------------

// Get all feedback with joins
$sql = "
    SELECT f.*,
           b.first_name AS bfirst, 
           b.last_name AS blast,
           p.$programCol AS program_name,
           u.$userCol AS staffname
    FROM feedback f
    LEFT JOIN beneficiaries b ON f.beneficiary_id = b.id
    LEFT JOIN programs p ON f.program_id = p.id
    LEFT JOIN users u ON f.staff_id = u.ptpID
    ORDER BY f.created_at DESC
";
$res = $conn->query($sql);
if (!$res) {
    die("<div class='alert alert-danger'>SQL Error: " . $conn->error . "</div>");
}

// Get beneficiaries for dropdown
$beneficiaries = $conn->query("SELECT id, first_name, last_name FROM beneficiaries ORDER BY first_name");

// Get programs for dropdown
$programs = $conn->query("SELECT id, $programCol AS program_name FROM programs ORDER BY $programCol");

// Calculate statistics
$totalFeedback = $res->num_rows;
$avgRating = $conn->query("SELECT AVG(rating) as avg FROM feedback WHERE rating > 0")->fetch_assoc()['avg'] ?? 0;
$pendingCount = $conn->query("SELECT COUNT(*) as c FROM feedback WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$approvedCount = $conn->query("SELECT COUNT(*) as c FROM feedback WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$rejectedCount = $conn->query("SELECT COUNT(*) as c FROM feedback WHERE status='rejected'")->fetch_assoc()['c'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback Management - ALNAAS</title>
<style>
/* Main Layout */
.main-content { 
    margin-left: 260px; 
    padding: 30px; 
    min-height: 100vh; 
    background: #f4f6fa;
}

body.dark .main-content {
    background: #0e1117;
}

.container-page { 
    width: 100%; 
    max-width: 1400px; 
    margin: 0 auto; 
}

/* Page Header */
.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: #003087;
    margin-bottom: 0.5rem;
}

body.dark .page-title {
    color: #5b9dff;
}

/* Statistics Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-box {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s;
    border-left: 4px solid #003087;
}

.stat-box:hover {
    transform: translateY(-5px);
}

body.dark .stat-box {
    background: #1a1f3a;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.stat-box h6 { 
    font-size: 0.85rem; 
    color: #666;
    text-transform: uppercase; 
    letter-spacing: 0.5px;
    margin-bottom: 0.8rem;
}

body.dark .stat-box h6 {
    color: #8b92b0;
}

.stat-box .stat-value { 
    font-size: 2.5rem; 
    font-weight: 700;
    color: #003087;
}

body.dark .stat-box .stat-value {
    color: #5b9dff;
}

.stat-box.stat-success { border-left-color: #00d4aa; }
.stat-box.stat-success .stat-value { color: #00d4aa; }

.stat-box.stat-warning { border-left-color: #ffc107; }
.stat-box.stat-warning .stat-value { color: #ffc107; }

.stat-box.stat-danger { border-left-color: #dc3545; }
.stat-box.stat-danger .stat-value { color: #dc3545; }

/* Cards */
.card { 
    border: none; 
    border-radius: 15px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    background: white;
}

body.dark .card { 
    background: #1a1f3a; 
    color: #c5cad9; 
}

.card-header {
    background: rgba(0,48,135,0.05);
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    padding: 1rem 1.5rem;
}

body.dark .card-header { 
    background: rgba(0,48,135,0.2);
    color: #5b9dff;
    border-bottom-color: #2a3150;
}

/* Table Styling */
.table { 
    margin-bottom: 0;
    font-size: 0.9rem;
}

body.dark .table { 
    color: #c5cad9; 
}

.table thead th {
    background: rgba(0,48,135,0.1);
    color: #003087;
    font-weight: 600;
    border: none;
    padding: 1rem;
}

body.dark .table thead th { 
    background: rgba(0,48,135,0.3);
    color: #5b9dff; 
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

body.dark .table tbody td {
    border-bottom-color: #2a3150;
}

.table-hover tbody tr:hover { 
    background: rgba(0,48,135,0.03);
}

body.dark .table-hover tbody tr:hover { 
    background: rgba(0,48,135,0.1); 
}

/* Action Buttons */
.action-btns { 
    display: flex; 
    gap: 0.3rem; 
    justify-content: flex-end; 
    flex-wrap: wrap; 
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

/* Form Controls */
.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem;
}

body.dark .form-control, 
body.dark .form-select { 
    background: #2a3150; 
    border-color: #3a4266; 
    color: #c5cad9; 
}

.form-control:focus, .form-select:focus {
    border-color: #003087;
    box-shadow: 0 0 0 0.2rem rgba(0,48,135,0.15);
}

/* Modals */
.modal-content {
    border-radius: 15px;
    border: none;
}

body.dark .modal-content { 
    background: #1a1f3a; 
    color: #c5cad9; 
}

.modal-header {
    border-bottom: 2px solid #e9ecef;
}

body.dark .modal-header {
    border-bottom-color: #2a3150;
}

.modal-title {
    color: #003087;
    font-weight: 600;
}

body.dark .modal-title {
    color: #5b9dff;
}

body.dark .btn-close {
    filter: invert(1);
}

/* Badges */
.badge {
    padding: 0.5rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Stars */
.stars-rating {
    color: #ffc107;
    font-size: 1.2rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #999;
}

body.dark .empty-state {
    color: #666;
}

.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 1rem;
}

/* Feedback Detail Box */
.feedback-detail-box {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #003087;
    white-space: pre-wrap;
    word-wrap: break-word;
}

body.dark .feedback-detail-box {
    background: #2a3150;
}

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .action-btns {
        justify-content: center;
    }
}
</style>
</head>
<body>

<div class="main-content">
<div class="container-page">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-title"><i class="bi bi-chat-left-text"></i> Feedback Management</h2>
            <span class="badge bg-primary">Role: <?= e($role) ?></span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($alert): ?>
        <div class="alert alert-<?= e($alert['type']) ?> alert-dismissible fade show">
            <?= e($alert['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-row">
        <div class="stat-box">
            <h6>Total Feedback</h6>
            <div class="stat-value"><?= $totalFeedback ?></div>
        </div>
        <div class="stat-box stat-success">
            <h6>Average Rating</h6>
            <div class="stat-value"><?= number_format($avgRating, 1) ?> <span style="font-size: 1.2rem;">★</span></div>
        </div>
        <div class="stat-box stat-warning">
            <h6>Pending</h6>
            <div class="stat-value"><?= $pendingCount ?></div>
        </div>
        <div class="stat-box stat-success">
            <h6>Approved</h6>
            <div class="stat-value"><?= $approvedCount ?></div>
        </div>
        <div class="stat-box stat-danger">
            <h6>Rejected</h6>
            <div class="stat-value"><?= $rejectedCount ?></div>
        </div>
    </div>

    <div class="row g-3">
        <!-- LEFT: Feedback List -->
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul"></i> All Feedback Entries</span>
                    <small class="text-muted">Latest first</small>
                </div>

                <div class="card-body p-0">
                    <?php if ($res->num_rows === 0): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h5>No Feedback Yet</h5>
                            <p class="text-muted">Submit the first feedback using the form on the right!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Beneficiary</th>
                                        <th>Program</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Staff</th>
                                        <th>Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $i=1; while ($row = $res->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <?php if ($row['anonymous'] == 1): ?>
                                                <span class="text-muted"><i class="bi bi-incognito"></i> Anonymous</span>
                                            <?php else: ?>
                                                <?= e($row['bfirst'] . ' ' . $row['blast']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($row['program_name'] ?? 'General') ?></td>
                                        <td>
                                            <span class="stars-rating">
                                                <?= str_repeat('★', intval($row['rating'] ?? 0)) ?>
                                                <?= str_repeat('☆', 5 - intval($row['rating'] ?? 0)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                $badgeClass = match($row['status']) {
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    default => 'warning'
                                                };
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($row['status']) ?></span>
                                        </td>
                                        <td><?= e($row['staffname'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <!-- View -->
                                                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#viewModal<?= $row['id'] ?>" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <?php if (in_array($role, ['Admin','Staff'])): ?>
                                                    <!-- Approve -->
                                                    <?php if ($row['status'] !== 'approved'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="fid" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button class="btn btn-sm btn-success" title="Approve" onclick="return confirm('Approve this feedback?')">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>

                                                    <!-- Reject -->
                                                    <?php if ($row['status'] !== 'rejected'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="fid" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Reject this feedback?')">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Delete (Admin only) -->
                                                <?php if ($role === 'Admin'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="fid" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete permanently?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- VIEW MODAL -->
                                    <div class="modal fade" id="viewModal<?= $row['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Feedback #<?= $row['id'] ?> Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Beneficiary:</strong><br>
                                                            <?= $row['anonymous'] == 1 ? '<em class="text-muted">Anonymous</em>' : e($row['bfirst'] . ' ' . $row['blast']) ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Program:</strong><br>
                                                            <?= e($row['program_name'] ?? 'General Feedback') ?>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Rating:</strong><br>
                                                            <span style="color: #ffc107; font-size: 1.5rem;">
                                                                <?= str_repeat('★', intval($row['rating'] ?? 0)) ?>
                                                                <?= str_repeat('☆', 5 - intval($row['rating'] ?? 0)) ?>
                                                            </span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Status:</strong><br>
                                                            <span class="badge bg-<?= $badgeClass ?> fs-6"><?= ucfirst($row['status']) ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <strong>Feedback:</strong>
                                                        <div class="feedback-detail-box mt-2">
                                                            <?= nl2br(e($row['feedback_text'])) ?>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <small class="text-muted"><strong>Recorded by:</strong> <?= e($row['staffname'] ?? 'Unknown') ?></small>
                                                        </div>
                                                        <div class="col-md-6 text-end">
                                                            <small class="text-muted"><strong>Date:</strong> <?= date('M d, Y \a\t h:i A', strtotime($row['created_at'])) ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <?php if (in_array($role, ['Admin','Staff'])): ?>
                                                        <?php if ($row['status'] !== 'approved'): ?>
                                                        <form method="post">
                                                            <input type="hidden" name="fid" value="<?= $row['id'] ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button class="btn btn-success" onclick="return confirm('Approve?')">
                                                                <i class="bi bi-check-lg"></i> Approve
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>

                                                        <?php if ($row['status'] !== 'rejected'): ?>
                                                        <form method="post">
                                                            <input type="hidden" name="fid" value="<?= $row['id'] ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button class="btn btn-danger" onclick="return confirm('Reject?')">
                                                                <i class="bi bi-x-lg"></i> Reject
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if ($role === 'Admin'): ?>
                                                        <form method="post">
                                                            <input type="hidden" name="fid" value="<?= $row['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button class="btn btn-outline-danger" onclick="return confirm('Delete permanently?')">
                                                                <i class="bi bi-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Add Feedback Form -->
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-plus-circle"></i> Submit New Feedback
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="submit_feedback" value="1">

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-person"></i> Beneficiary *</label>
                            <select name="beneficiary_id" class="form-select" required>
                                <option value="">-- Select Beneficiary --</option>
                                <?php if ($beneficiaries && $beneficiaries->num_rows): 
                                    while ($b = $beneficiaries->fetch_assoc()): ?>
                                    <option value="<?= $b['id'] ?>"><?= e($b['first_name'].' '.$b['last_name']) ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-briefcase"></i> Program (optional)</label>
                            <select name="program_id" class="form-select">
                                <option value="">-- General Feedback --</option>
                                <?php if ($programs && $programs->num_rows): 
                                    while ($p = $programs->fetch_assoc()): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['program_name']) ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-chat-text"></i> Feedback *</label>
                            <textarea name="feedback_text" class="form-control" rows="5" required placeholder="Enter beneficiary feedback here..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-star"></i> Rating *</label>
                            <select name="rating" class="form-select" required>
                                <option value="">-- Select Rating --</option>
                                <?php for ($r=1; $r<=5; $r++): ?>
                                    <option value="<?= $r ?>"><?= str_repeat('★', $r) ?> <?= $r ?> Star<?= $r>1?'s':'' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="anon" name="anonymous">
                            <label class="form-check-label" for="anon">
                                <i class="bi bi-incognito"></i> Submit as anonymous
                            </label>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">
                            <i class="bi bi-send"></i> Submit Feedback
                        </button>
                    </form>

                    <hr class="my-3">

                    <div class="alert alert-info mb-0" style="font-size: 0.85rem;">
                        <strong><i class="bi bi-info-circle"></i> Notes:</strong>
                        <ul class="mb-0 mt-2" style="font-size: 0.8rem;">
                            <li>New feedback starts with <strong>pending</strong> status</li>
                            <li>Staff/Admin can approve or reject feedback</li>
                            <li>Only Admin can permanently delete feedback</li>
                            <li>Anonymous feedback hides beneficiary name</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php
require_once __DIR__ . '/footer.php';
?>