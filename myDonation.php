<?php
session_start();
include __DIR__.'/alnaasDB.php';

// --- Check login ---
if(!isset($_SESSION['username'])){
    header("Location: alnaasLogin.php");
    exit();
}

$user_id = $_SESSION['ptpID'] ?? 0;
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Donor';

// --- Handle New Donation Submission ---
$success_msg = '';
$error_msg = '';
if(isset($_POST['addDonation'])){
    $category = $_POST['category'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $program_id = $_POST['program_id'] ?: null;
    $payment_method = $_POST['payment_method'] ?? '';
    $mobile_network = $_POST['mobile_network'] ?? null;
    $bank_reference = $_POST['bank_reference'] ?? null;

    // Validate money donations
    if($category === 'Money' && ($amount <= 0 || !is_numeric($amount))){
        $error_msg = 'Please enter a valid donation amount.';
    } else {
        $stmt = $conn->prepare("INSERT INTO donations 
            (donor_id, program_id, category, amount, status, payment_method, created_at) 
            VALUES (?, ?, ?, ?, 'Pending', ?, NOW())");
        if($stmt){
            // Combine payment method info
            if($payment_method === 'Mobile Money' && $mobile_network){
                $payment_method_full = $payment_method.' - '.$mobile_network;
            } elseif($payment_method === 'Bank Transfer' && $bank_reference){
                $payment_method_full = $payment_method.' - Ref: '.$bank_reference;
            } else {
                $payment_method_full = $payment_method ?: 'N/A';
            }

            $stmt->bind_param("iisss", $user_id, $program_id, $category, $amount, $payment_method_full);
            if($stmt->execute()){
                $success_msg = 'Donation added successfully! Status: Pending';
            } else {
                $error_msg = 'Error adding donation: '.$stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Database prepare failed: '.$conn->error;
        }
        header("Location: myDonation.php");
        exit();
    }
}

// --- Fetch Donations ---
$donations = [];
$query = "SELECT d.id, d.amount, d.category, d.status, d.payment_method, d.created_at, p.name AS program_name
          FROM donations d
          LEFT JOIN programs p ON d.program_id=p.id
          WHERE d.donor_id=?
          ORDER BY d.created_at DESC";
$stmt = $conn->prepare($query);
if($stmt){
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res) $donations = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- Total Money Donated ---
$totalAmount = 0;
foreach($donations as $d){
    if(strtolower($d['status'])==='completed' && strtolower($d['category'])==='money'){
        $totalAmount += $d['amount'];
    }
}

// --- Fetch Programs ---
$programs = [];
$res = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");
if($res) $programs = $res->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Donations | ALNAAS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#e6f0ff; font-family:'Poppins', sans-serif; color:#222; }
.sidebar { height:100vh; width:250px; position:fixed; top:0; left:0; background:#003087; color:white; padding-top:20px; }
.sidebar a { display:block; padding:12px 25px; color:#ddd; text-decoration:none; margin:5px 10px; border-radius:6px; }
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.2); color:#fff; }
.main-content { margin-left:260px; padding:20px; }
.card { border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,0.1); margin-bottom:20px; }
.table th, .table td { vertical-align: middle; }
.header-space { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
h2, h5, h6 { color:#003087; }
.btn-primary { background:#0059b3; border:none; }
.btn-primary:hover { background:#003087; }
.badge { font-size:0.8rem; }
</style>
</head>
<body>

<!-- Sidebar / Header -->
<div class="sidebar">
    <h3 class="text-center">ALNAAS</h3>
    <a href="alnaasDash.php"><i class="bi bi-house-fill"></i> Dashboard</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="header-space">
        <h2>My Donations</h2>
        <span>Welcome, <strong><?= htmlspecialchars($username) ?></strong></span>
    </div>

    <!-- Messages -->
    <?php if($success_msg) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
    <?php if($error_msg) echo "<div class='alert alert-danger'>$error_msg</div>"; ?>

    <!-- Add Donation Form -->
    <div class="card p-4">
        <h5>Add New Donation</h5>
        <form method="POST">
            <div class="row g-3">
                <!-- Category -->
                <div class="col-md-3">
                    <label class="form-label">Donation Type</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Money">Money</option>
                        <option value="Clothes">Clothes</option>
                        <option value="Food">Food</option>
                        <option value="Others">Others</option>
                    </select>
                </div>

                <!-- Program -->
                <div class="col-md-3">
                    <label class="form-label">Program (Optional)</label>
                    <select name="program_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach($programs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Amount -->
                <div class="col-md-3">
                    <label class="form-label">Amount (Money only)</label>
                    <input type="number" name="amount" class="form-control" placeholder="0">
                </div>

                <!-- Payment Method -->
                <div class="col-md-3">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-select" required>
                        <option value="">Select Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="PayPal">PayPal</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Mobile Money -->
                <div class="col-md-3 d-none" id="mobile_money_options">
                    <label class="form-label">Mobile Network</label>
                    <select name="mobile_network" class="form-select">
                        <option value="">Select Network</option>
                        <option value="MTN">MTN</option>
                        <option value="Airtel">Airtel</option>
                    </select>
                </div>

                <!-- Bank Transfer Reference -->
                <div class="col-md-3 d-none" id="bank_transfer_options">
                    <label class="form-label">Bank Reference / Transaction ID</label>
                    <input type="text" name="bank_reference" class="form-control" placeholder="Enter reference">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="addDonation" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Donation</button>
            </div>
        </form>
    </div>

    <!-- Total Donated -->
    <div class="card p-3 mb-4">
        <h6>Total Money Donated: UGX<?= number_format($totalAmount, 0) ?></h6>
    </div>

    <!-- Donations Table -->
    <div class="card p-4">
        <h5>Your Donations</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Amount</th>
                        <th>Category</th>
                        <th>Program</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($donations)): ?>
                        <?php foreach($donations as $d):
                            $status_class = match(strtolower($d['status'])){
                                'completed' => 'text-success',
                                'pending' => 'text-warning',
                                'failed' => 'text-danger',
                                default => 'text-secondary',
                            };
                        ?>
                        <tr>
                            <td><?= strtolower($d['category'])==='money' ? 'UGX'.number_format($d['amount'],0) : '—' ?></td>
                            <td><?= htmlspecialchars($d['category']) ?></td>
                            <td><?= htmlspecialchars($d['program_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($d['payment_method']) ?></td>
                            <td class="<?= $status_class ?>"><?= htmlspecialchars($d['status']) ?></td>
                            <td><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted">No donations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show/hide payment fields
document.getElementById('payment_method').addEventListener('change', function() {
    const value = this.value;
    document.getElementById('mobile_money_options').classList.toggle('d-none', value !== 'Mobile Money');
    document.getElementById('bank_transfer_options').classList.toggle('d-none', value !== 'Bank Transfer');
});
</script>
</body>
</html>
