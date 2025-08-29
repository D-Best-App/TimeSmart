<?php
require_once '../auth/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

define('IN_ADMIN_PAGE', true);
require_once './reports_helper.php';

// === INPUTS ===
$startDate = $_POST['start'] ?? '';
$endDate = $_POST['end'] ?? '';
$employeeID = $_POST['emp'] ?? '';
$rounding = intval($_POST['rounding'] ?? 0);
$separatePages = intval($_POST['separate_pages'] ?? 0);

if (!$startDate || !$endDate) {
    header('Location: ../error.php?code=400&message=' . urlencode('Start and end dates are required.'));
    exit;
}

// === DATA FETCH ===
$summaryData = getSummaryData($conn, $startDate, $endDate, $employeeID, $rounding);
$groupedData = $summaryData['grouped'];
$totals = $summaryData['totals'];

// === CREATE SPREADSHEET ===
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0); // we'll add sheets dynamically

$currentUser = '';
$totalHours = 0;
$sheet = null;
$rangeFormatted = date('m-d', strtotime($startDate)) . '_' . date('m-d', strtotime($endDate));

foreach ($groupedData as $empId => $data) {
    $fullName = $data['name'];
    $rowNum = 2;

    // Create new sheet if needed
    if ($separatePages && $employeeID == '' && $currentUser !== $empId) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($fullName, 0, 31)); // Excel sheet name limit

        // Headers
        $headers = ['Employee', 'Date', 'Time In', 'Time Out', 'Lunch Start', 'Lunch End', 'Rounded Hours'];
        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:G1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0078D7']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $currentUser = $empId;
    }

    // Default (single sheet) or first time through
    if (!$separatePages && !$sheet) {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');
        $headers = ['Employee', 'Date', 'Time In', 'Time Out', 'Lunch Start', 'Lunch End', 'Rounded Hours'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0078D7']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    foreach ($data['rows'] as $row) {
        $sheet->setCellValue("A{$rowNum}", $fullName);
        $sheet->setCellValue("B{$rowNum}", date('m/d/Y', strtotime($row['Date'])));
        $sheet->setCellValue("C{$rowNum}", $row['TimeIN']);
        $sheet->setCellValue("D{$rowNum}", $row['TimeOUT']);
        $sheet->setCellValue("E{$rowNum}", $row['LunchStart']);
        $sheet->setCellValue("F{$rowNum}", $row['LunchEnd']);
        $sheet->setCellValue("G{$rowNum}", $row['RoundedHours']);
        $rowNum++;
    }
    $totalHours += $totals[$empId];

    // Add total row for this employee/sheet if pages are separate
    if ($separatePages && $sheet) {
        $sheet->setCellValue("F{$rowNum}", 'Total Hours');
        $sheet->setCellValue("G{$rowNum}", number_format($totals[$empId], 2));
        $sheet->getStyle("F{$rowNum}:G{$rowNum}")->getFont()->setBold(true);
    }
}

// Add total row if not using separate pages
if (!$separatePages && $sheet) {
    $sheet->setCellValue("F{$rowNum}", 'Total Hours');
    $sheet->setCellValue("G{$rowNum}", number_format($totalHours, 2));
    $sheet->getStyle("F{$rowNum}:G{$rowNum}")->getFont()->setBold(true);
}

// Set active sheet to first
$spreadsheet->setActiveSheetIndex(0);

// Output Excel file
$employeeLabel = !empty($employeeID) ? preg_replace('/[^a-zA-Z0-9]/', '', $groupedData[$employeeID]['name']) : 'All';
$filename = "Payroll_Summary_{$employeeLabel}_{$rangeFormatted}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;