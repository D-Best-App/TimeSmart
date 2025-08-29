<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../auth/db.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

define('IN_ADMIN_PAGE', true);
require_once './reports_helper.php';

$start = $_POST['start'] ?? '';
$end = $_POST['end'] ?? '';
$emp = $_POST['emp'] ?? '';
$rounding = intval($_POST['rounding'] ?? 0);
$separatePages = intval($_POST['separate_pages'] ?? 0);

if (!$start || !$end) {
    header('Location: ../error.php?code=400&message=' . urlencode('Start and end dates are required.'));
    exit;
}

// === DATA FETCH ===
$summaryData = getSummaryData($conn, $start, $end, $emp, $rounding);
$grouped = $summaryData['grouped'];
$totals = $summaryData['totals'];

// PDF Setup
$pdf = new TCPDF();
$pdf->SetCreator('TimeClock System');
$pdf->SetAuthor('D-Best Technologies');
$pdf->SetTitle('Payroll Summary Report');
$pdf->SetMargins(15, 15, 15);
$pdf->SetFont('helvetica', '', 11);

// Page for each user if requested
$first = true;
foreach ($grouped as $empId => $data) {
    if (!$first && $separatePages) {
        $pdf->AddPage();
    } else if ($first) {
        $pdf->AddPage();
        $first = false;
    }

    $pdf->SetFont('helvetica', '', 11);
    $name = htmlspecialchars($data['name']);
    $html = '<h2 style="text-align:center; color:#0078D7;">Payroll Summary Report</h2>';
    $html .= "<p><strong>Employee:</strong> $name<br>";
    $html .= "<strong>Date Range:</strong> " . date("m/d/Y", strtotime($start)) . " to " . date("m/d/Y", strtotime($end)) . "</p>";

    $html .= '<table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <thead style="background-color: #e6f0ff;">
                    <tr>
                        <th><b>Date</b></th>
                        <th><b>Time In</b></th>
                        <th><b>Time Out</b></th>
                        <th><b>Lunch Start</b></th>
                        <th><b>Lunch End</b></th>
                        <th><b>Rounded Hours</b></th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($data['rows'] as $r) {
        $date = date("m/d/Y", strtotime($r['Date']));
        $html .= "<tr>
                    <td>$date</td>
                    <td>{$r['TimeIN']}</td>
                    <td>{$r['TimeOUT']}</td>
                    <td>{$r['LunchStart']}</td>
                    <td>{$r['LunchEnd']}</td>
                    <td style='text-align:right;'>" . number_format($r['RoundedHours'], 2) . "</td>
                  </tr>";
    }

    $html .= "<tr style='font-weight:bold; background-color:#f1f1f1;'>
                <td colspan='5'>Total Hours</td>
                <td style='text-align:right;'>" . number_format($totals[$empId], 2) . "</td>
              </tr>";

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// Clean buffer and output PDF
while (ob_get_level()) ob_end_clean();
$pdf->Output('payroll_summary.pdf', 'D');
exit;