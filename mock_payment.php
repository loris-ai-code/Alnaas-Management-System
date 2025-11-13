<?php
session_start();
include 'alnaasDB.php';

if($conn->connect_error){ die("Connection failed: " . $conn->connect_error); }

$amount = $_GET['amount'] ?? 0;
$gateway = $_GET['gateway'] ?? 'mock';

if($amount < 1){
    die("Invalid amount");
}

$transaction_id = "MOCK-".time();
$status = 'Completed'; // For mock demo, we can mark as completed

$stmt = $conn->prepare("INSERT INTO donations (donor_id, amount, payment_method, transaction_id, status) VALUES (?,?,?,?,?)");
if(!$stmt){
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("idsss", $_SESSION['ptpID'], $amount, $gateway, $transaction_id, $status);
$stmt->execute();
$stmt->close();

// Redirect to mock success page
header("Location: donation_success.php?amount=$amount&gateway=$gateway");
exit();
