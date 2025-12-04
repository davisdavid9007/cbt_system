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

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Get filters
    $class_filter = $_GET['class'] ?? '';
    $exam_filter = $_GET['exam_id'] ?? '';
    $subject_filter = $_GET['subject_id'] ?? '';

    // Build query (same as view_results.php)
    $query = "SELECT r.*, s.full_name, s.admission_number, s.class, e.exam_name, sub.subject_name 
              FROM results r 
              JOIN students s ON r.student_id = s.id 
              JOIN exams e ON r.exam_id = e.id 
              JOIN subjects sub ON e.subject_id = sub.id 
              WHERE 1=1";

    $params = [];

    if ($class_filter) {
        $query .= " AND s.class = ?";
        $params[] = $class_filter;
    }

    if ($exam_filter) {
        $query .= " AND r.exam_id = ?";
        $params[] = $exam_filter;
    }

    if ($subject_filter) {
        $query .= " AND e.subject_id = ?";
        $params[] = $subject_filter;
    }

    $query .= " ORDER BY s.class, s.full_name";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = ['Student Name', 'Admission No.', 'Class', 'Exam', 'Subject', 'Objective Score', 'Theory Score', 'Total Score', 'Percentage', 'Grade', 'Date Submitted'];
    $sheet->fromArray($headers, NULL, 'A1');

    // Add data
    $row = 2;
    foreach ($results as $result) {
        $sheet->setCellValue('A' . $row, $result['full_name']);
        $sheet->setCellValue('B' . $row, $result['admission_number']);
        $sheet->setCellValue('C' . $row, $result['class']);
        $sheet->setCellValue('D' . $row, $result['exam_name']);
        $sheet->setCellValue('E' . $row, $result['subject_name']);
        $sheet->setCellValue('F' . $row, $result['objective_score']);
        $sheet->setCellValue('G' . $row, $result['theory_score']);
        $sheet->setCellValue('H' . $row, $result['total_score']);
        $sheet->setCellValue('I' . $row, $result['percentage']);
        $sheet->setCellValue('J' . $row, $result['grade']);
        $sheet->setCellValue('K' . $row, $result['submitted_at']);
        $row++;
    }

    // Style headers
    $sheet->getStyle('A1:K1')->getFont()->setBold(true);
    $sheet->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F81BD');
    $sheet->getStyle('A1:K1')->getFont()->getColor()->setARGB('FFFFFFFF');

    // Auto size columns
    foreach (range('A', 'K') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Set headers for download
    $filename = 'exam_results_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Write file to output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}