<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Include PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('A1', 'QUESTION');
$sheet->setCellValue('B1', 'OPTION_A');
$sheet->setCellValue('C1', 'OPTION_B');
$sheet->setCellValue('D1', 'OPTION_C');
$sheet->setCellValue('E1', 'OPTION_D');
$sheet->setCellValue('F1', 'CORRECT_ANSWER');

// Set sample data
$sheet->setCellValue('A2', 'What is 15 + 27?');
$sheet->setCellValue('B2', '32');
$sheet->setCellValue('C2', '42');
$sheet->setCellValue('D2', '38');
$sheet->setCellValue('E2', '45');
$sheet->setCellValue('F2', 'B');

$sheet->setCellValue('A3', 'Which of these is a proper fraction?');
$sheet->setCellValue('B3', '5/4');
$sheet->setCellValue('C3', '4/4');
$sheet->setCellValue('D3', '3/4');
$sheet->setCellValue('E3', '7/4');
$sheet->setCellValue('F3', 'C');

// Style the headers
$sheet->getStyle('A1:F1')->getFont()->setBold(true);
$sheet->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F81BD');
$sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB('FFFFFFFF');

// Auto size columns
foreach(range('A','F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="objective_questions_template.xlsx"');
header('Cache-Control: max-age=0');

// Write file to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;