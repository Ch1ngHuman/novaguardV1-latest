<?php
session_start();
require_once '../connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Helper function to safely handle htmlspecialchars with null values
function safe_htmlspecialchars($value) {
    return htmlspecialchars($value ?? '');
}

// Handle CSV export
// Handle XLSX (Excel) export with bold headers
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $selected_year_id = $_GET['school_year_id'] ?? null;
    $selected_semester_id = !empty($_GET['semester_id']) ? $_GET['semester_id'] : null;
    $report_type = $_GET['report_type'] ?? 'summary';

    if ($selected_year_id) {
        // Generate report data
        $report_data = generateViolationSummaryReport($conn, $selected_year_id, $selected_semester_id);

        // Require phpspreadsheet autoloader
        $autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');
        if (!$autoloadPath || !file_exists($autoloadPath)) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<p>PhpSpreadsheet not found. Please install with: composer require phpoffice/phpspreadsheet</p>';
            exit();
        }
        require_once $autoloadPath;

        // Create spreadsheet and populate
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

    // Header row (remove Last Violation Date column — column removed from DB)
    // Added 'Date Completed' column between Date Reported and Sanctions
    $headers = ['Name', 'Year&Course', 'First Violation Date', 'Violation 1', 'Violation 2', 'Violation 3', 'Violation 4', 'Violation 5', 'Date Reported', 'Date Completed', 'Sanction', 'Comment/s'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }

    // Make header bold (A1:L1)
    $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        // Fill data rows
        $rowNum = 2;
        foreach ($report_data as $row) {
            $violations = array_map('trim', explode(', ', $row['violations'] ?? ''));

            // student, year_course
            $student_name = $row['student_name'] ?? '';
            $year_course = '';
            if ($row['student_type'] === 'jhs') {
                $year_course = 'JHS ' . ($row['student_level'] ?? '');
            } elseif ($row['student_type'] === 'shs') {
                $year_course = 'SHS ' . ($row['student_level'] ?? '') . ' ' . ($row['student_course_strand'] ?? '');
            } elseif ($row['student_type'] === 'college') {
                $year_course = 'COLLEGE ' . ($row['student_level'] ?? '') . ' ' . ($row['student_course_strand'] ?? '');
            }

            $first_violation_date = !empty($row['first_violation']) ? date('M d, Y', strtotime($row['first_violation'])) : '';
            $date_reported = !empty($row['first_violation']) ? date('M d, Y', strtotime($row['first_violation'])) : '';
            $date_completed = '';
            if (!empty($row['date_completed'])) {
                $date_completed = date('M d, Y', strtotime($row['date_completed']));
            } elseif (!empty($row['student_id'])) {
                // Fallback: fetch date_completed from student_summary_archive if not present in report row
                $ssa_stmt = $conn->prepare("SELECT date_completed FROM student_summary_archive WHERE student_id = ? AND student_type = ? AND school_year_id = ? LIMIT 1");
                if ($ssa_stmt) {
                    $ssa_stmt->bind_param('isi', $row['student_id'], $row['student_type'], $selected_year_id);
                    $ssa_stmt->execute();
                    $ssa_res = $ssa_stmt->get_result();
                    if ($ssa_row = $ssa_res->fetch_assoc()) {
                        if (!empty($ssa_row['date_completed'])) {
                            $date_completed = date('M d, Y', strtotime($ssa_row['date_completed']));
                        }
                    }
                    $ssa_stmt->close();
                }
            }

            // sanction
            $sanction = '';
            if (isset($row['sanction_status']) && $row['sanction_status'] !== 'Completed') {
                $sanction_text = $row['sanction_status'] ?? '';
                if (strpos($sanction_text, 'Incomplete') !== false) {
                    preg_match('/(\d+)\s*hours?/', $sanction_text, $matches);
                    $hours = $matches[1] ?? '0';
                    $sanction = $hours . ' hours community service';
                } else {
                    $sanction = $sanction_text;
                }
            } else {
                $total_hours = 0;
                if (!empty($row['student_id'])) {
                    $ssa_stmt = $conn->prepare("SELECT total_sanction_hours FROM student_summary_archive WHERE student_id = ? AND student_type = ? AND school_year_id = ? LIMIT 1");
                    if ($ssa_stmt) {
                        $ssa_stmt->bind_param('isi', $row['student_id'], $row['student_type'], $selected_year_id);
                        $ssa_stmt->execute();
                        $ssa_res = $ssa_stmt->get_result();
                        if ($ssa_row = $ssa_res->fetch_assoc()) {
                            $total_hours = (int)$ssa_row['total_sanction_hours'];
                        }
                        $ssa_stmt->close();
                    }
                }
                $sanction = 'Completed (' . $total_hours . ' hours)';
            }

            // Write cells
            $sheet->setCellValue('A' . $rowNum, $student_name);
            $sheet->setCellValue('B' . $rowNum, $year_course);
            $sheet->setCellValue('C' . $rowNum, $first_violation_date);
            $sheet->setCellValue('D' . $rowNum, $violations[0] ?? '');
            $sheet->setCellValue('E' . $rowNum, $violations[1] ?? '');
            $sheet->setCellValue('F' . $rowNum, $violations[2] ?? '');
            $sheet->setCellValue('G' . $rowNum, $violations[3] ?? '');
            $sheet->setCellValue('H' . $rowNum, $violations[4] ?? '');
            $sheet->setCellValue('I' . $rowNum, $date_reported);
            $sheet->setCellValue('J' . $rowNum, $date_completed);
            $sheet->setCellValue('K' . $rowNum, $sanction);
            // Comment/s left blank intentionally
            $sheet->setCellValue('L' . $rowNum, '');

            $rowNum++;
        }

        // Auto-size columns (optional)
        foreach (range('A', 'L') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Stream to browser — build filename using selected school year label if available
        $xlsxYearLabel = 'report';
        if (!empty($selected_year_id)) {
            // Fetch the school_year label directly from the database to avoid relying on
            // $school_years (which is defined later in the file) and prevent undefined
            // variable / foreach warnings when this export runs early.
            $sy_stmt = $conn->prepare("SELECT school_year FROM school_year WHERE id = ? LIMIT 1");
            if ($sy_stmt) {
                $sy_stmt->bind_param('i', $selected_year_id);
                $sy_stmt->execute();
                $sy_res = $sy_stmt->get_result();
                if ($sy_row = $sy_res->fetch_assoc()) {
                    $xlsxYearLabel = $sy_row['school_year'];
                }
                $sy_stmt->close();
            }
        }
        // Sanitize filename portion
        $xlsxYearLabel = preg_replace('/[^A-Za-z0-9_\-]/', '_', $xlsxYearLabel);

        $filename = 'violation_report_' . $xlsxYearLabel . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }
}

// CSV export removed — XLSX export is the preferred method now

// Handle PDF export with improved error handling
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $selected_year_id = $_GET['school_year_id'] ?? null;
    $selected_semester_id = !empty($_GET['semester_id']) ? $_GET['semester_id'] : null;
    $report_type = $_GET['report_type'] ?? 'summary';

    if ($selected_year_id && $report_type === 'summary') {
        // Check if mbstring extension is loaded
        if (!extension_loaded('mbstring')) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>PDF Export Error</title>';
            echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .error{background:#f8d7da;color:#721c24;padding:20px;border-radius:5px;border:1px solid #f5c6cb;} .btn{background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;margin-top:15px;}</style>';
            echo '</head><body>';
            echo '<div class="error">';
            echo '<h3>PDF Export Not Available</h3>';
            echo '<p>The mbstring PHP extension is required for PDF export but is not enabled on this server.</p>';
            echo '<p><strong>Alternative options:</strong></p>';
            echo '<ul><li>Use the "Print Report" button and save as PDF from your browser</li>';
            echo '<li>Export the data as CSV format instead</li>';
            echo '<li>Contact your system administrator to enable the mbstring extension</li></ul>';
            echo '</div>';
            echo '<a href="javascript:history.back()" class="btn">← Go Back</a>';
            echo '</body></html>';
            exit();
        }

        // Get school year info
        $year_query = "SELECT school_year FROM school_year WHERE id = ?";
        $year_stmt = $conn->prepare($year_query);
        $year_stmt->bind_param('i', $selected_year_id);
        $year_stmt->execute();
        $year_info = $year_stmt->get_result()->fetch_assoc();

        // Get semester info if provided
        $semester_info = null;
        if ($selected_semester_id) {
            $semester_query = "SELECT semester_name FROM semesters WHERE id = ?";
            $semester_stmt = $conn->prepare($semester_query);
            $semester_stmt->bind_param('i', $selected_semester_id);
            $semester_stmt->execute();
            $semester_info = $semester_stmt->get_result()->fetch_assoc();
        }

        // Generate report data
        $report_data = generateViolationSummaryReport($conn, $selected_year_id, $selected_semester_id);

        // Check if Dompdf is available
        $autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');
        if (!$autoloadPath || !file_exists($autoloadPath)) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>PDF Library Error</title>';
            echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .error{background:#f8d7da;color:#721c24;padding:20px;border-radius:5px;border:1px solid #f5c6cb;} .btn{background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;margin-top:15px;}</style>';
            echo '</head><body>';
            echo '<div class="error">';
            echo '<h3>PDF Library Not Found</h3>';
            echo '<p>The PDF generation library (Dompdf) is not installed.</p>';
            echo '<p>Please install it by running: <code>composer require dompdf/dompdf</code></p>';
            echo '<p><strong>Alternative options:</strong></p>';
            echo '<ul><li>Use the "Print Report" button and save as PDF from your browser</li>';
            echo '<li>Export the data as CSV format instead</li></ul>';
            echo '</div>';
            echo '<a href="javascript:history.back()" class="btn">← Go Back</a>';
            echo '</body></html>';
            exit();
        }

        require_once $autoloadPath;

        if (!class_exists('Dompdf\\Dompdf')) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>PDF Class Error</title>';
            echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .error{background:#f8d7da;color:#721c24;padding:20px;border-radius:5px;border:1px solid #f5c6cb;} .btn{background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;margin-top:15px;}</style>';
            echo '</head><body>';
            echo '<div class="error">';
            echo '<h3>PDF Library Not Available</h3>';
            echo '<p>The PDF generation class is not available. Please check the Dompdf installation.</p>';
            echo '<p><strong>Alternative options:</strong></p>';
            echo '<ul><li>Use the "Print Report" button and save as PDF from your browser</li>';
            echo '<li>Export the data as CSV format instead</li></ul>';
            echo '</div>';
            echo '<a href="javascript:history.back()" class="btn">← Go Back</a>';
            echo '</body></html>';
            exit();
        }

        try {
            // Set PHP internal encoding to UTF-8 if function exists
            if (function_exists('mb_internal_encoding')) {
                mb_internal_encoding('UTF-8');
            }
            
            // Configure Dompdf with safer options
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false); // Disable remote content for security
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isFontSubsettingEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('debugPng', false);
            $options->set('debugKeepTemp', false);
            $options->set('debugCss', false);
            $options->set('debugLayout', false);
            $options->set('debugLayoutLines', false);
            $options->set('debugLayoutBlocks', false);
            $options->set('debugLayoutInline', false);
            $options->set('debugLayoutPaddingBox', false);
            
            $dompdf = new \Dompdf\Dompdf($options);

            // Build clean HTML for PDF
            $title = 'Violation Report - ' . ($year_info['school_year'] ?? '');
            if ($semester_info) {
                $title .= ' - ' . $semester_info['semester_name'];
            }
            $dateGenerated = date('M d, Y h:i A');

            $rowsHtml = '';
            foreach ($report_data as $row) {
                // Parse violations into individual columns (without dates)
                $violations = explode(', ', $row['violations'] ?? '');
                $violations = array_map('trim', $violations);
                
                // Clean and escape data properly
                $studentName = trim($row['student_name'] ?? '');
                $yearCourse = '';
                
                if ($row['student_type'] === 'jhs') {
                    $yearCourse = 'JHS ' . ($row['student_level'] ?? '');
                } elseif ($row['student_type'] === 'shs') {
                    $yearCourse = 'SHS ' . ($row['student_level'] ?? '') . ' ' . ($row['student_course_strand'] ?? '');
                } elseif ($row['student_type'] === 'college') {
                    $yearCourse = 'COLLEGE ' . ($row['student_level'] ?? '') . ' ' . ($row['student_course_strand'] ?? '');
                }
                
                // Format first violation date
                $firstViolationDate = '';
                if (!empty($row['first_violation'])) {
                    $firstViolationDate = date('M d, Y', strtotime($row['first_violation']));
                }
                
                // Format sanction
                $sanction = '';
                $remarks = 'DONE';
                
                if (isset($row['sanction_status']) && $row['sanction_status'] !== 'Completed') {
                    $sanction_text = $row['sanction_status'] ?? '';
                    if (strpos($sanction_text, 'Incomplete') !== false) {
                        // Extract hours from "Incomplete (X hours)"
                        preg_match('/(\d+)\s*hours?/', $sanction_text, $matches);
                        $hours = $matches[1] ?? '0';
                        $sanction = $hours . ' hours community service';
                        $remarks = 'INCOMPLETE';
                    } else {
                        $sanction = $sanction_text;
                        $remarks = 'INCOMPLETE';
                    }
                } else {
                    // Completed - show total hours from student_summary_archive if available
                    $total_hours = 0;
                    if (!empty($row['student_id'])) {
                        $ssa_stmt = $conn->prepare("SELECT total_sanction_hours FROM student_summary_archive WHERE student_id = ? AND student_type = ? AND school_year_id = ? LIMIT 1");
                        if ($ssa_stmt) {
                            $ssa_stmt->bind_param('isi', $row['student_id'], $row['student_type'], $selected_year_id);
                            $ssa_stmt->execute();
                            $ssa_res = $ssa_stmt->get_result();
                            if ($ssa_row = $ssa_res->fetch_assoc()) {
                                $total_hours = (int)$ssa_row['total_sanction_hours'];
                            }
                            $ssa_stmt->close();
                        }
                    }
                    $sanction = 'Completed (' . $total_hours . ' hours)';
                }
                
                // Use htmlspecialchars with explicit encoding
                $studentName = htmlspecialchars($studentName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $yearCourse = htmlspecialchars($yearCourse, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $firstViolationDate = htmlspecialchars($firstViolationDate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $sanction = htmlspecialchars($sanction, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $remarks = htmlspecialchars($remarks, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Determine date reported and date completed
                $dateReportedHtml = htmlspecialchars($firstViolationDate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $dateCompletedHtml = '';
                if (!empty($row['date_completed'])) {
                    $dateCompletedHtml = htmlspecialchars(date('M d, Y', strtotime($row['date_completed'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                } else {
                    // try to fetch from archive if available
                    if (!empty($row['student_id'])) {
                        $ssa_stmt = $conn->prepare("SELECT date_completed FROM student_summary_archive WHERE student_id = ? AND student_type = ? AND school_year_id = ? LIMIT 1");
                        if ($ssa_stmt) {
                            $ssa_stmt->bind_param('isi', $row['student_id'], $row['student_type'], $selected_year_id);
                            $ssa_stmt->execute();
                            $ssa_res = $ssa_stmt->get_result();
                            if ($ssa_row = $ssa_res->fetch_assoc()) {
                                if (!empty($ssa_row['date_completed'])) {
                                    $dateCompletedHtml = htmlspecialchars(date('M d, Y', strtotime($ssa_row['date_completed'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                }
                            }
                            $ssa_stmt->close();
                        }
                    }
                }

                $rowsHtml .= '<tr>'
                    . '<td>' . $studentName . '</td>'
                    . '<td>' . $yearCourse . '</td>'
                    . '<td>' . $firstViolationDate . '</td>'
                    . '<td>' . htmlspecialchars($violations[0] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($violations[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($violations[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($violations[3] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($violations[4] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
                    . '<td>' . $dateReportedHtml . '</td>'
                    . '<td>' . $dateCompletedHtml . '</td>'
                    . '<td>' . $sanction . '</td>'
                    . '<td>' . $remarks . '</td>'
                    . '</tr>';
            }

            // Simple, clean HTML that works better with Dompdf
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 10px; 
            margin: 15px; 
            color: #000;
        }
        h2 { 
            margin: 0 0 10px 0; 
            color: #333; 
            font-size: 14px; 
            text-align: center;
        }
        .meta { 
            color: #666; 
            margin-bottom: 15px; 
            font-size: 9px; 
            text-align: center;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
        }
        th, td { 
            border: 1px solid #ccc; 
            padding: 4px; 
            font-size: 8px; 
            vertical-align: top;
        }
        th { 
            background: #f0f0f0; 
            font-weight: bold; 
            text-align: center;
        }
        td:nth-child(5) { 
            text-align: center; 
        }
        tr:nth-child(even) { 
            background: #f9f9f9; 
        }
    </style>
</head>
<body>
    <h2>' . htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h2>
    <div class="meta">Generated: ' . htmlspecialchars($dateGenerated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Year&Course</th>
                <th>First Violation Date</th>
                <th>Violation 1</th>
                <th>Violation 2</th>
                <th>Violation 3</th>
                <th>Violation 4</th>
                <th>Violation 5</th>
                <th>Date Reported</th>
                <th>Date Completed</th>
                <th>Sanction</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
    </table>
</body>
</html>';

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            $filename = 'violation_report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $year_info['school_year'] ?? 'report');
            if ($semester_info) {
                $filename .= '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $semester_info['semester_name']);
            }
            $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
            exit();

        } catch (Exception $e) {
            // Enhanced error handling with user-friendly message
            error_log("PDF Generation Error: " . $e->getMessage());
            
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>PDF Generation Error</title>';
            echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .error{background:#f8d7da;color:#721c24;padding:20px;border-radius:5px;border:1px solid #f5c6cb;} .btn{background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;margin-top:15px;} .alternatives{background:#d4edda;color:#155724;padding:15px;border-radius:5px;border:1px solid #c3e6cb;margin:15px 0;}</style>';
            echo '</head><body>';
            echo '<div class="error">';
            echo '<h3>PDF Generation Failed</h3>';
            echo '<p>There was an error generating the PDF report. This might be due to server configuration or missing dependencies.</p>';
            echo '</div>';
            echo '<div class="alternatives">';
            echo '<h4>Alternative Options:</h4>';
            echo '<ul>';
            echo '<li><strong>Print to PDF:</strong> Use the "Print Report" button and save as PDF from your browser</li>';
            echo '<li><strong>CSV Export:</strong> Export the data as CSV format for use in Excel or other applications</li>';
            echo '<li><strong>Contact Support:</strong> If you need PDF functionality, contact your system administrator</li>';
            echo '</ul>';
            echo '</div>';
            echo '<a href="javascript:history.back()" class="btn">← Go Back</a>';
            echo '<!-- Error details for admin: ' . htmlspecialchars($e->getMessage()) . ' -->';
            echo '</body></html>';
            exit();
        }
    }
}

// Function to generate violation summary report
function generateViolationSummaryReport($conn, $school_year_id, $semester_id = null) {
    // Check if data exists in archive first, then current tables
    $archive_check = "SELECT COUNT(*) as count FROM violation_history_archive WHERE school_year_id = ?";
    $stmt = $conn->prepare($archive_check);
    $stmt->bind_param('i', $school_year_id);
    $stmt->execute();
    $archive_count = $stmt->get_result()->fetch_assoc()['count'];

    if ($archive_count > 0) {
        // Use archived data
        $query = "SELECT
                    vha.student_id,
                    vha.student_name,
                    vha.student_type,
                    vha.student_level,
                    vha.student_course_strand,
                    COUNT(*) as total_violations,
                    GROUP_CONCAT(DISTINCT vha.violation_type SEPARATOR ', ') as violations,
                    MIN(vha.date_recorded) as first_violation,
                    CASE
                        WHEN ss.id IS NULL OR ss.status = 'completed' THEN MAX(vha.date_recorded)
                        ELSE NULL
                    END AS last_violation,
                    vha.school_year,
                    CASE
                        WHEN ss.id IS NULL THEN 'Completed'
                        WHEN ss.status = 'active' THEN CONCAT(
                            'Incomplete (',
                            CASE
                                WHEN ss.sanction_hours IS NULL OR ss.sanction_hours = '' THEN '0 hours'
                                WHEN LOWER(ss.sanction_hours) LIKE '%hours%' THEN ss.sanction_hours
                                ELSE CONCAT(CAST(ss.sanction_hours AS UNSIGNED), ' hours')
                            END,
                            ')'
                        )
                        WHEN ss.status = 'completed' THEN 'Completed'
                        ELSE ss.status
                    END AS sanction_status
                  FROM violation_history_archive vha
                  LEFT JOIN student_sanctions ss
                    ON ss.student_id = vha.student_id
                   AND ss.student_type = vha.student_type
                   AND ss.school_year_id = ?
                  WHERE vha.school_year_id = ?";
        
        $params = [$school_year_id, $school_year_id];
        $param_types = 'ii';
        
        // Add semester filter if provided
        if ($semester_id) {
            $query .= " AND vha.semester_id = ?";
            $params[] = $semester_id;
            $param_types .= 'i';
        }
        
        $query .= " GROUP BY vha.student_id, vha.student_name, vha.student_type
                  ORDER BY vha.student_type, vha.student_name";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($param_types, ...$params);
    } else {
        // Use current data (for current school year)
        $query = "SELECT
                    s.id as student_id,
                    CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.lastName) as student_name,
                    'jhs' as student_type,
                    s.year_lvl as student_level,
                    '' as student_course_strand,
                    COUNT(v.id) as total_violations,
                    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
                    MIN(v.date_recorded) as first_violation,
                    CASE
                        WHEN ss.id IS NULL OR ss.status = 'completed' THEN MAX(v.date_recorded)
                        ELSE NULL
                    END AS last_violation,
                    sy.school_year,
                    CASE
                        WHEN ss.id IS NULL THEN 'Completed'
                        WHEN ss.status = 'active' THEN CONCAT(
                            'Incomplete (',
                            CASE
                                WHEN ss.sanction_hours IS NULL OR ss.sanction_hours = '' THEN '0 hours'
                                WHEN LOWER(ss.sanction_hours) LIKE '%hours%' THEN ss.sanction_hours
                                ELSE CONCAT(CAST(ss.sanction_hours AS UNSIGNED), ' hours')
                            END,
                            ')'
                        )
                        WHEN ss.status = 'completed' THEN 'Completed'
                        ELSE ss.status
                    END AS sanction_status
                  FROM jhs_students s
                  LEFT JOIN hs_violations v ON s.id = v.student_id AND v.school_year_id = ?
                  LEFT JOIN student_sanctions ss ON ss.student_id = s.id AND ss.student_type = 'jhs' AND ss.school_year_id = ?
                  JOIN school_year sy ON sy.id = ?
                  WHERE v.id IS NOT NULL
                  GROUP BY s.id

                  UNION ALL

                  SELECT
                    s.id as student_id,
                    CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.lastName) as student_name,
                    'shs' as student_type,
                    s.year_lvl as student_level,
                    s.strand as student_course_strand,
                    COUNT(v.id) as total_violations,
                    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
                    MIN(v.date_recorded) as first_violation,
                        CASE
                            WHEN ss.id IS NULL OR ss.status = 'completed' THEN MAX(v.date_recorded)
                            ELSE NULL
                        END AS last_violation,
                    sy.school_year,
                    CASE
                        WHEN ss.id IS NULL THEN 'Completed'
                        WHEN ss.status = 'active' THEN CONCAT(
                            'Incomplete (',
                            CASE
                                WHEN ss.sanction_hours IS NULL OR ss.sanction_hours = '' THEN '0 hours'
                                WHEN LOWER(ss.sanction_hours) LIKE '%hours%' THEN ss.sanction_hours
                                ELSE CONCAT(CAST(ss.sanction_hours AS UNSIGNED), ' hours')
                            END,
                            ')'
                        )
                        WHEN ss.status = 'completed' THEN 'Completed'
                        ELSE ss.status
                    END AS sanction_status
                  FROM shs_students s
                      LEFT JOIN shs_violations v ON s.id = v.student_id AND v.school_year_id = ?";
        
        // Initialize params with JHS placeholders (3) + SHS first placeholder (1)
        $params = [$school_year_id, $school_year_id, $school_year_id, $school_year_id];
        $param_types = 'iiii';
        
        // Add semester filter for SHS if provided
        if ($semester_id) {
            $query .= " AND v.semester_id = ?";
            $params[] = $semester_id;
            $param_types .= 'i';
        }
        
        $query .= " LEFT JOIN student_sanctions ss ON ss.student_id = s.id AND ss.student_type = 'shs' AND ss.school_year_id = ?
                  JOIN school_year sy ON sy.id = ?
                  WHERE v.id IS NOT NULL
                  GROUP BY s.id

                  UNION ALL

                  SELECT
                    c.id as student_id,
                    CONCAT(c.firstName, ' ', COALESCE(c.middleName, ''), ' ', c.LastName) as student_name,
                    'college' as student_type,
                    c.year_lvl as student_level,
                    c.course as student_course_strand,
                    COUNT(v.id) as total_violations,
                    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
                    MIN(v.date_recorded) as first_violation,
                          CASE
                              WHEN ss.id IS NULL OR ss.status = 'completed' THEN MAX(v.date_recorded)
                              ELSE NULL
                          END AS last_violation,
                    sy.school_year,
                    CASE
                        WHEN ss.id IS NULL THEN 'Completed'
                        WHEN ss.status = 'active' THEN CONCAT(
                            'Incomplete (',
                            CASE
                                WHEN ss.sanction_hours IS NULL OR ss.sanction_hours = '' THEN '0 hours'
                                WHEN LOWER(ss.sanction_hours) LIKE '%hours%' THEN ss.sanction_hours
                                ELSE CONCAT(CAST(ss.sanction_hours AS UNSIGNED), ' hours')
                            END,
                            ')'
                        )
                        WHEN ss.status = 'completed' THEN 'Completed'
                        ELSE ss.status
                    END AS sanction_status
                  FROM college_students c
                  LEFT JOIN (
                            SELECT student_id, violation_type, date_recorded, school_year_id, semester_id, id FROM college_violations
                      UNION ALL
                            SELECT student_id, violation_type, date_recorded, school_year_id, semester_id, id FROM college_crim_violations
                        ) v ON c.id = v.student_id AND v.school_year_id = ?";
        
        // Add SHS LEFT JOIN and school_year params
        $params[] = $school_year_id;  // For SHS ss.school_year_id
        $params[] = $school_year_id;  // For SHS sy.id
        $params[] = $school_year_id;  // For College v.school_year_id
        $param_types .= 'iii';
        
        // Add semester filter for College if provided
        if ($semester_id) {
            $query .= " AND v.semester_id = ?";
            $params[] = $semester_id;
            $param_types .= 'i';
        }
        
        $query .= " LEFT JOIN student_sanctions ss ON ss.student_id = c.id AND ss.student_type = 'college' AND ss.school_year_id = ?
                  JOIN school_year sy ON sy.id = ?
                  WHERE v.id IS NOT NULL
                  GROUP BY c.id

                  ORDER BY student_type, student_name";

        $params[] = $school_year_id;
        $params[] = $school_year_id;
        $param_types .= 'ii';

        $stmt = $conn->prepare($query);
        $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all school years for dropdown
$school_years_query = "SELECT id, school_year FROM school_year ORDER BY school_year DESC";
$school_years_result = $conn->query($school_years_query);
$school_years = $school_years_result->fetch_all(MYSQLI_ASSOC);

// Get all semesters for dropdown
$semesters_query = "SELECT id, semester_name FROM semesters ORDER BY semester_number ASC";
$semesters_result = $conn->query($semesters_query);
$semesters = $semesters_result->fetch_all(MYSQLI_ASSOC);

// Get current school year
$current_year_query = "SELECT id, school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$current_year_result = $conn->query($current_year_query);
$current_year = $current_year_result->fetch_assoc();

// Handle delete action
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_archive') {
    $delete_year_id = intval($_POST['school_year_id']);
    $entered_password = $_POST['password'] ?? '';

    // Check for active/incomplete sanctions first
    $sanctions_check = "SELECT COUNT(*) as count FROM student_sanctions 
                      WHERE school_year_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sanctions_check);
    $stmt->bind_param('i', $delete_year_id);
    $stmt->execute();
    $sanctions_result = $stmt->get_result()->fetch_assoc();

    if ($sanctions_result['count'] > 0) {
        $delete_message = "Cannot delete archive records. There is incomplete sanctions for this school year.";
        $delete_message_type = "error";
    } else {
        // Now verify password since there are no active sanctions
        $user_id = $_SESSION['user_id'];
        $password_check = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($password_check);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();

        if ($user_data && password_verify($entered_password, $user_data['password'])) {
            try {
                $conn->begin_transaction();

                // Delete from violation_history_archive
                $delete_violations = "DELETE FROM violation_history_archive WHERE school_year_id = ?";
                $stmt = $conn->prepare($delete_violations);
                $stmt->bind_param('i', $delete_year_id);
                $stmt->execute();
                $violations_deleted = $stmt->affected_rows;

                // Delete from student_summary_archive
                $delete_summaries = "DELETE FROM student_summary_archive WHERE school_year_id = ?";
                $stmt = $conn->prepare($delete_summaries);
                $stmt->bind_param('i', $delete_year_id);
                $stmt->execute();
                $summaries_deleted = $stmt->affected_rows;

                $conn->commit();

                // Get school year name for message
                $year_query = "SELECT school_year FROM school_year WHERE id = ?";
                $stmt = $conn->prepare($year_query);
                $stmt->bind_param('i', $delete_year_id);
                $stmt->execute();
                $year_info = $stmt->get_result()->fetch_assoc();

                $delete_message = "Successfully deleted archived data for " . ($year_info['school_year'] ?? 'Unknown Year');
                $delete_message_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $delete_message = "Error deleting archived data: " . $e->getMessage();
                $delete_message_type = "error";
            }
        } else {
            $delete_message = "Incorrect password. Archive deletion cancelled.";
            $delete_message_type = "error";
        }
    }
}

// This section was removed as it was a duplicate of the delete action code

// Handle form submission
$selected_year_id = $_GET['school_year_id'] ?? $current_year['id'];
$selected_semester_id = !empty($_GET['semester_id']) ? $_GET['semester_id'] : null;
$report_type = $_GET['report_type'] ?? 'summary';

// Get report data
$report_data = [];
if ($report_type === 'summary') {
    $report_data = generateViolationSummaryReport($conn, $selected_year_id, $selected_semester_id);
}

// Get selected school year info
$selected_year_info = array_filter($school_years, function($year) use ($selected_year_id) {
    return $year['id'] == $selected_year_id;
});
$selected_year_info = reset($selected_year_info);

// Get selected semester info
$selected_semester_info = null;
if ($selected_semester_id) {
    $selected_semester_info = array_filter($semesters, function($semester) use ($selected_semester_id) {
        return $semester['id'] == $selected_semester_id;
    });
    $selected_semester_info = reset($selected_semester_info);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - NovaGuard</title>
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        .report-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .report-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filter-group {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 10px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 150px;
        }
        .generate-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .generate-btn:hover {
            background: #5a6fd8;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .report-table th,
        .report-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .report-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        .report-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .report-table tr:hover {
            background: #e3f2fd;
        }
        .export-buttons {
            margin: 20px 0;
            text-align: right;
        }
        .export-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .export-btn:hover {
            background: #218838;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .delete-btn:hover {
            background: #c82333;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: none;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .warning-text {
            color: #dc3545;
            font-weight: bold;
            margin: 10px 0;
        }
        .message {
            padding: 10px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        /* Print-only header with logos (hidden on screen) */
        .print-header { display: none; }
        .print-header img { height: 60px; width: auto; object-fit: contain; }

        @media print {
            .report-filters, .export-buttons, .back-button,
            .icon-back-wrapper, .no-print, #searchInput, #departmentFilter { display: none !important; }
            .report-container { box-shadow: none; margin: 0; }
            /* Force table text to print in black */
            .report-table th,
            .report-table td { color: #000 !important; }
            /* Ensure header cells are readable on print */
            .report-table th { background: #fff !important; }
            .print-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
            .print-header .left, .print-header .right { width: 20%; }
            .print-header .title { width: 60%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div>
            <h1>Violation Summary</h1>
            <div class="icon-back-wrapper">
                <a href="settings.php" class="icon-back-btn" aria-label="Back to Dashboard">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Print-only header (visible only during print) -->
        <div class="print-header">
            <div class="left"><img src="../assets/icons/images/NovaScholaLogo.png" alt="Nova Schola Logo"></div>
            <div class="title"><h2  style="margin:0; color: #000000;">Violation Report - <?= safe_htmlspecialchars($selected_year_info['school_year'] ?? '') ?><?= $selected_semester_info ? ' - ' . safe_htmlspecialchars($selected_semester_info['semester_name']) : '' ?></h2></div>
            <div class="right"><img src="../assets/icons/images/NovaGuardLogo.png" alt="NovaGuard Logo"></div>
        </div>

        <!-- Display delete message if exists -->
        <?php if (isset($delete_message)): ?>
            <div class="message <?= $delete_message_type ?>">
                <?= safe_htmlspecialchars($delete_message) ?>
            </div>
        <?php endif; ?>

        <!-- Report Filters -->
        <div class="report-filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="school_year_id">School Year:</label>
                    <select name="school_year_id" id="school_year_id" onchange="this.form.submit()">
                        <?php foreach ($school_years as $year): ?>
                            <option value="<?= $year['id'] ?>" <?= $year['id'] == $selected_year_id ? 'selected' : '' ?>>
                                <?= safe_htmlspecialchars($year['school_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="semester_id">Semester:</label>
                    <select name="semester_id" id="semester_id" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $semester): ?>
                            <option value="<?= $semester['id'] ?>" <?= $semester['id'] == $selected_semester_id ? 'selected' : '' ?>>
                                <?= safe_htmlspecialchars($semester['semester_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Debug Information (remove in production) -->
        <?php if (isset($_GET['debug'])): ?>
            <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
                <h4>Debug Information:</h4>
                <p><strong>Selected Year ID:</strong> <?= $selected_year_id ?></p>
                <p><strong>Selected Year:</strong> <?= safe_htmlspecialchars($selected_year_info['school_year'] ?? 'Not found') ?></p>
                <p><strong>Report Data Count:</strong> <?= count($report_data) ?></p>

                <?php
                // Check archive count for debug
                $debug_archive_check = "SELECT COUNT(*) as count FROM violation_history_archive WHERE school_year_id = ?";
                $debug_stmt = $conn->prepare($debug_archive_check);
                $debug_stmt->bind_param('i', $selected_year_id);
                $debug_stmt->execute();
                $debug_archive_count = $debug_stmt->get_result()->fetch_assoc()['count'];
                ?>
                <p><strong>Archive Records for this Year:</strong> <?= $debug_archive_count ?></p>

                <?php
                // Check current data count for debug
                $debug_current_check = "SELECT COUNT(*) as jhs_count FROM hs_violations WHERE school_year_id = ?";
                $debug_stmt2 = $conn->prepare($debug_current_check);
                $debug_stmt2->bind_param('i', $selected_year_id);
                $debug_stmt2->execute();
                $debug_current_count = $debug_stmt2->get_result()->fetch_assoc()['jhs_count'];
                ?>
                <p><strong>Current JHS Violations for this Year:</strong> <?= $debug_current_count ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($report_data)): ?>
            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-number"><?= count($report_data) ?></div>
                    <div class="stat-label">Students with Violations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= array_sum(array_column($report_data, 'total_violations')) ?></div>
                    <div class="stat-label">Total Violations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($report_data, function($r) { return $r['student_type'] === 'jhs'; })) ?></div>
                    <div class="stat-label">JHS Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($report_data, function($r) { return $r['student_type'] === 'shs'; })) ?></div>
                    <div class="stat-label">SHS Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($report_data, function($r) { return $r['student_type'] === 'college'; })) ?></div>
                    <div class="stat-label">College Students</div>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button onclick="window.print()" class="export-btn">Print Report</button>
                <a href="?school_year_id=<?= urlencode($selected_year_id ?? '') ?>&semester_id=<?= urlencode($selected_semester_id ?? '') ?>&report_type=summary&export=xlsx" class="export-btn">Export as Excel (.xlsx)</a>
                <!-- <a href="?school_year_id=<?= urlencode($selected_year_id ?? '') ?>&semester_id=<?= urlencode($selected_semester_id ?? '') ?>&report_type=summary&export=pdf" class="export-btn">Export as PDF</a> -->
                <?php
                // Only show delete button if there's archived data for this year
                $archive_check_query = "SELECT COUNT(*) as count FROM violation_history_archive WHERE school_year_id = ?";
                $archive_stmt = $conn->prepare($archive_check_query);
                $archive_stmt->bind_param('i', $selected_year_id);
                $archive_stmt->execute();
                $has_archive_data = $archive_stmt->get_result()->fetch_assoc()['count'] > 0;

                if ($has_archive_data): ?>
                    <button onclick="openDeleteModal()" class="delete-btn">Delete Archive Records</button>
                <?php endif; ?>
            </div>
            
            <!-- Report Table -->
            <h2 class="no-print" style="color: #000000;">Violation Report - <?= safe_htmlspecialchars($selected_year_info['school_year'] ?? '') ?><?= $selected_semester_info ? ' - ' . safe_htmlspecialchars($selected_semester_info['semester_name']) : '' ?></h2>
            <!-- Search, Filter Controls & Semester Selection -->
            <div style="margin: 20px 0; display: flex; justify-content: center; gap: 2em; align-items: center;">
                <input type="text" id="searchInput" placeholder="Search by name..." style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 220px;" autocomplete="off">
                <select id="departmentFilter" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="all">All Sanctions</option>
                    <option value="completed">Completed</option>
                    <option value="incomplete">Incomplete</option>
                </select>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Type</th>
                        <th>Level/Year</th>
                        <th>Course/Strand</th>
                        <th>Total Violations</th>
                        <th>Violation Types</th>
                        <th>Date Reported</th>
                        <th>Date Completed</th>
                        <th>Sanctions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?= safe_htmlspecialchars($row['student_name']) ?></td>
                            <td><?= strtoupper($row['student_type'] ?? '') ?></td>
                            <td><?= safe_htmlspecialchars($row['student_level']) ?></td>
                            <td><?= safe_htmlspecialchars($row['student_course_strand']) ?></td>
                            <td><?= $row['total_violations'] ?? 0 ?></td>
                            <td><?= safe_htmlspecialchars($row['violations']) ?></td>
                            <td><?= $row['first_violation'] ? date('M d, Y', strtotime($row['first_violation'])) : '-' ?></td>
                            <td>
                                <?php
                                    // Date Completed: prefer value from report_data, fall back to archive table if needed
                                    $date_completed_display = '-';
                                    if (!empty($row['date_completed'])) {
                                        $date_completed_display = date('M d, Y', strtotime($row['date_completed']));
                                    } elseif (!empty($row['student_id'])) {
                                        $ssa_stmt = $conn->prepare("SELECT date_completed FROM student_summary_archive WHERE student_id = ? AND student_type = ? AND school_year_id = ? LIMIT 1");
                                        if ($ssa_stmt) {
                                            $ssa_stmt->bind_param('isi', $row['student_id'], $row['student_type'], $selected_year_id);
                                            $ssa_stmt->execute();
                                            $ssa_res = $ssa_stmt->get_result();
                                            if ($ssa_row = $ssa_res->fetch_assoc()) {
                                                if (!empty($ssa_row['date_completed'])) {
                                                    $date_completed_display = date('M d, Y', strtotime($ssa_row['date_completed']));
                                                }
                                            }
                                            $ssa_stmt->close();
                                        }
                                    }
                                    echo safe_htmlspecialchars($date_completed_display);
                                ?>
                            </td>
                            <td>
                                <?php
                                    // Display 'Completed (N hours)' when sanction is Completed, otherwise show status
                                    $sanction_display = '-';
                                    if (isset($row['sanction_status']) && $row['sanction_status'] !== 'Completed') {
                                        $sanction_display = $row['sanction_status'];
                                    } else {
                                        $total_hours = 0;
                                        if (!empty($row['student_id'])) {
                                            $ssa_stmt = $conn->prepare("SELECT total_sanction_hours FROM student_summary_archive WHERE student_id = ? AND student_type = ? AND school_year_id = ? LIMIT 1");
                                            if ($ssa_stmt) {
                                                $ssa_stmt->bind_param('isi', $row['student_id'], $row['student_type'], $selected_year_id);
                                                $ssa_stmt->execute();
                                                $ssa_res = $ssa_stmt->get_result();
                                                if ($ssa_row = $ssa_res->fetch_assoc()) {
                                                    $total_hours = (int)$ssa_row['total_sanction_hours'];
                                                }
                                                $ssa_stmt->close();
                                            }
                                        }
                                        $sanction_display = 'Completed (' . $total_hours . ' hours)';
                                    }

                                    echo safe_htmlspecialchars($sanction_display);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <h3>No violation data found for the selected school year.</h3>
                <p>Try selecting a different school year or check if violations have been recorded.</p>
                <p><strong>Note:</strong> Historical data will appear here once you archive previous school years.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Archive Records</h3>
            </div>

            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete_archive">
                <input type="hidden" name="school_year_id" value="<?= $selected_year_id ?>">

                <div class="warning-text">
                    WARNING: This action will permanently delete all archived violation and summary records for
                    "<?= safe_htmlspecialchars($selected_year_info['school_year'] ?? 'Unknown Year') ?>". This cannot be undone!
                </div>

                <div class="form-group">
                    <label for="password">Enter your account password to confirm:</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Your account password">
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button class="cancel-btn" type="button" onclick="closeDeleteModal()"
                            style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; margin-right: 10px;">
                        Cancel
                    </button>
                    <button type="submit" class="delete-btn">
                        Delete Archive Records
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
            document.getElementById('password').focus();
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('password').value = '';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }

        // Handle form submission: rely on modal + required password; no browser alerts/confirms
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const pwdInput = document.getElementById('password');
            if (!pwdInput.checkValidity()) {
                e.preventDefault();
                // Show native validity message near the input
                pwdInput.reportValidity();
                return false;
            }
            // Proceed with submission; user has already reviewed the warning in the modal
        });

        // Search and Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const departmentFilter = document.getElementById('departmentFilter');
            const table = document.querySelector('.report-table');
            
            if (!table) return; // Exit if table doesn't exist
            
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            function filterTable() {
                const searchTerm = searchInput.value.trim().toLowerCase();
                const filterValue = departmentFilter.value;

                rows.forEach(row => {
                    const nameCell = row.querySelector('td:first-child'); // Student Name column
                    const sanctionCell = row.querySelector('td:last-child'); // Sanctions column
                    
                    if (!nameCell || !sanctionCell) return;

                    const studentName = nameCell.textContent.toLowerCase();
                    const sanctionStatus = sanctionCell.textContent.toLowerCase();

                    // Check search filter
                    const matchesSearch = searchTerm === '' || studentName.includes(searchTerm);
                    
                    // Check sanction status filter
                    let matchesFilter = true;
                    if (filterValue === 'completed') {
                        // Match only statuses that explicitly contain 'completed'
                        matchesFilter = sanctionStatus.indexOf('completed') !== -1;
                    } else if (filterValue === 'incomplete') {
                        // Match only statuses that explicitly contain 'incomplete'
                        // Some statuses contain 'Incomplete (X hours)' — normalize and check for 'incomplete'
                        matchesFilter = sanctionStatus.indexOf('incomplete') !== -1;
                    }
                    // If filterValue is 'all', matchesFilter remains true

                    // Show/hide row based on both filters
                    if (matchesSearch && matchesFilter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update statistics after filtering
                updateFilteredStats();
            }

            function updateFilteredStats() {
                const visibleRows = rows.filter(row => row.style.display !== 'none');
                const visibleData = visibleRows.map(row => {
                    const cells = row.querySelectorAll('td');
                    return {
                        student_type: cells[1].textContent.toLowerCase(),
                        total_violations: parseInt(cells[4].textContent) || 0
                    };
                });

                // Update stat cards with filtered data
                const statCards = document.querySelectorAll('.stat-card');
                if (statCards.length >= 5) {
                    statCards[0].querySelector('.stat-number').textContent = visibleData.length; // Students with Violations
                    statCards[1].querySelector('.stat-number').textContent = visibleData.reduce((sum, item) => sum + item.total_violations, 0); // Total Violations
                    statCards[2].querySelector('.stat-number').textContent = visibleData.filter(item => item.student_type === 'jhs').length; // JHS Students
                    statCards[3].querySelector('.stat-number').textContent = visibleData.filter(item => item.student_type === 'shs').length; // SHS Students
                    statCards[4].querySelector('.stat-number').textContent = visibleData.filter(item => item.student_type === 'college').length; // College Students
                }
            }

            // Add event listeners
            searchInput.addEventListener('input', filterTable);
            departmentFilter.addEventListener('change', filterTable);
            
            // Initial filter call
            filterTable();
        });
    </script>
</body>
</html>