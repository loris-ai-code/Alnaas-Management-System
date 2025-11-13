<?php
// Include Dompdf manually
require_once __DIR__ . '/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// Initialize Dompdf
$dompdf = new Dompdf();

// Database connection
$conn = new mysqli("localhost", "root", "loro", "alnaasDB");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { width: 120px; }
        .header h1 { color: #0059b3; margin: 5px 0; }
        .header h3 { color: #444; margin: 0; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; color: #333; }
        tr:nth-child(even) { background-color: #f9f9f9; }

        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 12px; color: #555; }

        .signature { height: 50px; }
    </style>
</head>
<body>

<div class="header">
    <img src="assets/PTPlogo.jpg" alt="Alnaas Logo" />
    <h1>Alnaas Management System</h1>
    <h3>Custom PDF Report</h3>
</div>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Date</th>
        <th>Reported By</th>
        <th>Approved By</th>
        <th>Signature</th>
    </tr>';

// Fetch data from the database
$result = $conn->query("SELECT * FROM reports");
if($result){
    while($row = $result->fetch_assoc()){
        // Assuming your table has reported_by, approved_by, and signature columns
        $html .= "<tr>
            <td>{$row['id']}</td>
            <td>{$row['name']}</td>
            <td>{$row['date']}</td>
            <td>{$row['reported_by']}</td>
            <td>{$row['approved_by']}</td>
            <td><img src='assets/{$row['signature']}' class='signature' /></td>
        </tr>";
    }
} else {
    $html .= '<tr><td colspan="6">No records found</td></tr>';
}

$html .= '
</table>

<div class="footer">
    &copy; '.date("Y").' Alnaas Management System. All rights reserved.
</div>

</body>
</html>
';

// Load HTML into Dompdf
$dompdf->loadHtml($html);

// Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the PDF
$dompdf->render();

// Output the PDF to browser for download
$dompdf->stream("Custom_Alnaas_Report.pdf", ["Attachment" => true]);

$conn->close();
?>
