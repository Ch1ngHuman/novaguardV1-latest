<?php
session_start();
require_once '../connect.php';

$cancelUrl = '../pages/main-menu.html';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $cancelUrl = 'settings.php';
    } elseif ($_SESSION['role'] === 'guard') {
        $cancelUrl = 'guard-dashboard.php';
    }
}

// Function to read CSV files
function readCSV($filename) {
    $rows = array();
    if (($handle = fopen($filename, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

// Function to convert Excel to CSV (requires user to save as CSV)
function validateFileFormat($filename) {
    $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return $fileExtension === 'csv';
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $department = $_POST['department'] ?? '';
    $uploadedFile = $_FILES['csv_file'];
    
    // Check if file was uploaded successfully
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $fileName = $uploadedFile['name'];
        $fileTmpName = $uploadedFile['tmp_name'];
        $fileSize = $uploadedFile['size'];
        
        // Check if file is CSV format
        if (validateFileFormat($fileName)) {
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $newFileName = $department . '_import_' . date('Y-m-d_H-i-s') . '.csv';
            $uploadPath = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                try {
                    // Read the CSV file
                    $rows = readCSV($uploadPath);
                    
                    if (empty($rows)) {
                        throw new Exception("The CSV file appears to be empty.");
                    }
                    
                    // Check if we have headers
                    $headers = array_shift($rows); // Remove and get header row
                    
                    // Validate headers (case-insensitive) depending on department
                    $normalizedHeaders = array_map('strtolower', array_map('trim', $headers));

                    // Determine header expectations based on department
                    if ($department === 'shs') {
                        $expectedHeaders = ['id', 'firstname', 'middlename', 'lastname', 'email', 'strand', 'year_lvl', 'school_year'];
                        $minColumns = 8;
                        $requiredPositions = [
                            1 => 'firstname',
                            3 => 'lastname',
                            5 => 'strand',
                            6 => 'year_lvl',
                            7 => 'school_year'
                        ];
                    } elseif ($department === 'college') {
                        $expectedHeaders = ['id', 'firstname', 'middlename', 'lastname', 'email', 'course', 'year_lvl', 'school_year'];
                        $minColumns = 8;
                        $requiredPositions = [
                            1 => 'firstname',
                            3 => 'lastname',
                            5 => 'course',
                            6 => 'year_lvl',
                            7 => 'school_year'
                        ];
                    } else { // default JHS expectations
                        $expectedHeaders = ['id', 'firstname', 'middlename', 'lastname', 'email', 'year_lvl', 'school_year'];
                        $minColumns = 7;
                        $requiredPositions = [
                            1 => 'firstname',
                            3 => 'lastname', 
                            5 => 'year_lvl',
                            6 => 'school_year'
                        ];
                    }

                    $headerValid = true;
                    $headerMessage = "";

                    if (count($normalizedHeaders) < $minColumns) {
                        $headerValid = false;
                        $headerMessage = "CSV file must have at least {$minColumns} columns. Found: " . count($normalizedHeaders) . " columns.\nYour headers: " . implode(', ', $headers);
                    } else {
                        // Check if essential columns exist by position and name
                        $missingCols = [];
                        foreach ($requiredPositions as $pos => $expectedCol) {
                            if (!isset($normalizedHeaders[$pos]) || 
                                (strpos($normalizedHeaders[$pos], strtolower($expectedCol)) === false && 
                                 strpos($normalizedHeaders[$pos], str_replace('_', '', strtolower($expectedCol))) === false)) {
                                $missingCols[] = "Column " . ($pos + 1) . " should be '$expectedCol' but found '" . ($headers[$pos] ?? 'missing') . "'";
                            }
                        }

                        if (!empty($missingCols)) {
                            $headerValid = false;
                            $headerMessage = "Column mismatch:\n" . implode("\n", $missingCols);
                        }
                    }

                    if (!$headerValid) {
                        throw new Exception($headerMessage . "\nExpected columns: " . implode(', ', $expectedHeaders));
                    }
                    
                    // Counter for successful imports
                    $importCount = 0;
                    $errors = [];
                    $duplicateEmails = [];

                    if ($department === 'jhs') {
                        // Check database connection
                        if ($conn->connect_error) {
                            throw new Exception("Database connection failed: " . $conn->connect_error);
                        }
                        
                        // Prepare the insert statement
                        $stmt = $conn->prepare("INSERT INTO jhs_students (firstName, middleName, lastName, email, year_lvl, school_year) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        if (!$stmt) {
                            throw new Exception("Database prepare error: " . $conn->error);
                        }
                        
                        foreach ($rows as $rowIndex => $row) {
                            $actualRowNum = $rowIndex + 2; // Account for header row
                            
                            // Skip completely empty rows
                            if (empty(array_filter($row, function($cell) { return trim($cell) !== ''; }))) {
                                continue;
                            }
                            
                            // Ensure we have enough columns
                            if (count($row) < 7) {
                                $errors[] = "Row {$actualRowNum}: Insufficient columns (found " . count($row) . ", need 7)";
                                continue;
                            }
                            
                            // Clean data
                            $firstName = trim($row[1] ?? '');
                            $middleName = trim($row[2] ?? '');
                            $lastName = trim($row[3] ?? '');
                            $email = trim($row[4] ?? '');
                            $yearLevel = trim($row[5] ?? '');
                            $schoolYear = trim($row[6] ?? '');
                            
                            // Skip if essential fields are empty
                            if (empty($firstName)) {
                                $errors[] = "Row {$actualRowNum}: First name is required";
                                continue;
                            }
                            
                            if (empty($lastName)) {
                                $errors[] = "Row {$actualRowNum}: Last name is required";
                                continue;
                            }
                            
                            if (empty($yearLevel)) {
                                $errors[] = "Row {$actualRowNum}: Year level is required";
                                continue;
                            }
                            
                            if (empty($schoolYear)) {
                                $errors[] = "Row {$actualRowNum}: School year is required";
                                continue;
                            }
                            
                            // Validate year level
                            $validYearLevels = ['Grade7', 'Grade8', 'Grade9', 'Grade10'];
                            if (!in_array($yearLevel, $validYearLevels)) {
                                $errors[] = "Row {$actualRowNum}: Invalid year level '{$yearLevel}' for {$firstName} {$lastName}. Must be one of: " . implode(', ', $validYearLevels);
                                continue;
                            }
                            
                            // Validate school year format (YYYY-YYYY)
                            if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {
                                $errors[] = "Row {$actualRowNum}: Invalid school year format '{$schoolYear}' for {$firstName} {$lastName}. Format should be YYYY-YYYY (e.g., 2024-2025)";
                                continue;
                            }
                            
                            // Validate email if provided
                            if (!empty($email)) {
                                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $errors[] = "Row {$actualRowNum}: Invalid email format '{$email}' for {$firstName} {$lastName}";
                                    continue;
                                }
                                
                                // Check for duplicate email in the same file
                                if (in_array(strtolower($email), $duplicateEmails)) {
                                    $errors[] = "Row {$actualRowNum}: Duplicate email '{$email}' found in file";
                                    continue;
                                }
                                $duplicateEmails[] = strtolower($email);
                            }
                            
                            try {
                                $stmt->bind_param("ssssss", 
                                    $firstName,
                                    $middleName,
                                    $lastName,
                                    $email,
                                    $yearLevel,
                                    $schoolYear
                                );
                                
                                if ($stmt->execute()) {
                                    $importCount++;
                                } else {
                                    // Check if it's a duplicate email error
                                    if (strpos($stmt->error, 'email_unique') !== false || strpos($stmt->error, 'Duplicate entry') !== false) {
                                        $errors[] = "Row {$actualRowNum}: Email '{$email}' already exists in database for {$firstName} {$lastName}";
                                    } else {
                                        $errors[] = "Row {$actualRowNum}: Database error for {$firstName} {$lastName}: " . $stmt->error;
                                    }
                                }
                            } catch (Exception $e) {
                                $errors[] = "Row {$actualRowNum}: Error importing {$firstName} {$lastName}: " . $e->getMessage();
                            }
                        }
                        
                        $stmt->close();
                    } elseif ($department === 'shs') {
                        // Check database connection
                        if ($conn->connect_error) {
                            throw new Exception("Database connection failed: " . $conn->connect_error);
                        }

                        // Prepare the insert statement for SHS
                        $stmt = $conn->prepare("INSERT INTO shs_students (firstName, middleName, lastName, email, strand, year_lvl, school_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception("Database prepare error: " . $conn->error);
                        }

                        $validYearLevels = ['Grade11', 'Grade12'];
                        $validStrands = ['STEM', 'HUMSS', 'ABM', 'TVL'];

                        foreach ($rows as $rowIndex => $row) {
                            $actualRowNum = $rowIndex + 2; // Account for header row

                            // Skip completely empty rows
                            if (empty(array_filter($row, function($cell) { return trim($cell) !== ''; }))) {
                                continue;
                            }

                            // Ensure we have enough columns
                            if (count($row) < 8) {
                                $errors[] = "Row {$actualRowNum}: Insufficient columns (found " . count($row) . ", need 8)";
                                continue;
                            }

                            // Clean data
                            $firstName = trim($row[1] ?? '');
                            $middleName = trim($row[2] ?? '');
                            $lastName = trim($row[3] ?? '');
                            $email = trim($row[4] ?? '');
                            $strand = strtoupper(trim($row[5] ?? ''));
                            $yearLevel = trim($row[6] ?? '');
                            $schoolYear = trim($row[7] ?? '');

                            // Required fields
                            if (empty($firstName)) {
                                $errors[] = "Row {$actualRowNum}: First name is required";
                                continue;
                            }
                            if (empty($lastName)) {
                                $errors[] = "Row {$actualRowNum}: Last name is required";
                                continue;
                            }
                            if (empty($email)) {
                                $errors[] = "Row {$actualRowNum}: Email is required";
                                continue;
                            }
                            if (empty($strand)) {
                                $errors[] = "Row {$actualRowNum}: Strand is required";
                                continue;
                            }
                            if (empty($yearLevel)) {
                                $errors[] = "Row {$actualRowNum}: Year level is required";
                                continue;
                            }
                            if (empty($schoolYear)) {
                                $errors[] = "Row {$actualRowNum}: School year is required";
                                continue;
                            }

                            // Validate year level
                            if (!in_array($yearLevel, $validYearLevels)) {
                                $errors[] = "Row {$actualRowNum}: Invalid year level '{$yearLevel}' for {$firstName} {$lastName}. Must be one of: " . implode(', ', $validYearLevels);
                                continue;
                            }

                            // Validate strand
                            if (!in_array($strand, $validStrands)) {
                                $errors[] = "Row {$actualRowNum}: Invalid strand '{$strand}' for {$firstName} {$lastName}. Must be one of: " . implode(', ', $validStrands);
                                continue;
                            }

                            // Validate school year format (YYYY-YYYY)
                            if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {
                                $errors[] = "Row {$actualRowNum}: Invalid school year format '{$schoolYear}' for {$firstName} {$lastName}. Format should be YYYY-YYYY (e.g., 2024-2025)";
                                continue;
                            }

                            // Validate email
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "Row {$actualRowNum}: Invalid email format '{$email}' for {$firstName} {$lastName}";
                                continue;
                            }

                            // Check for duplicate email in the same file
                            if (in_array(strtolower($email), $duplicateEmails)) {
                                $errors[] = "Row {$actualRowNum}: Duplicate email '{$email}' found in file";
                                continue;
                            }
                            $duplicateEmails[] = strtolower($email);

                            try {
                                $stmt->bind_param("sssssss",
                                    $firstName,
                                    $middleName,
                                    $lastName,
                                    $email,
                                    $strand,
                                    $yearLevel,
                                    $schoolYear
                                );

                                if ($stmt->execute()) {
                                    $importCount++;
                                } else {
                                    // Check if it's a duplicate email error
                                    if (strpos($stmt->error, 'email_unique') !== false || strpos($stmt->error, 'Duplicate entry') !== false) {
                                        $errors[] = "Row {$actualRowNum}: Email '{$email}' already exists in database for {$firstName} {$lastName}";
                                    } else {
                                        $errors[] = "Row {$actualRowNum}: Database error for {$firstName} {$lastName}: " . $stmt->error;
                                    }
                                }
                            } catch (Exception $e) {
                                $errors[] = "Row {$actualRowNum}: Error importing {$firstName} {$lastName}: " . $e->getMessage();
                            }
                        }

                        $stmt->close();
                    } elseif ($department === 'college') {
                        // Check database connection
                        if ($conn->connect_error) {
                            throw new Exception("Database connection failed: " . $conn->connect_error);
                        }

                        // Prepare the insert statement for College
                        $stmt = $conn->prepare("INSERT INTO college_students (firstName, middleName, lastName, email, course, year_lvl, school_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception("Database prepare error: " . $conn->error);
                        }

                        $validYearLevels = ['1stYear', '2ndYear', '3rdYear', '4thYear'];
                        // Course values are enforced by DB ENUM. We won't hardcode list here to avoid mismatch.

                        foreach ($rows as $rowIndex => $row) {
                            $actualRowNum = $rowIndex + 2; // Account for header row

                            // Skip completely empty rows
                            if (empty(array_filter($row, function($cell) { return trim($cell) !== ''; }))) {
                                continue;
                            }

                            // Ensure we have enough columns
                            if (count($row) < 8) {
                                $errors[] = "Row {$actualRowNum}: Insufficient columns (found " . count($row) . ", need 8)";
                                continue;
                            }

                            // Clean data
                            $firstName = trim($row[1] ?? '');
                            $middleName = trim($row[2] ?? '');
                            $lastName = trim($row[3] ?? '');
                            $email = trim($row[4] ?? '');
                            $course = trim($row[5] ?? '');
                            $yearLevel = trim($row[6] ?? '');
                            $schoolYear = trim($row[7] ?? '');

                            // Required fields
                            if (empty($firstName)) { $errors[] = "Row {$actualRowNum}: First name is required"; continue; }
                            if (empty($lastName)) { $errors[] = "Row {$actualRowNum}: Last name is required"; continue; }
                            if (empty($email)) { $errors[] = "Row {$actualRowNum}: Email is required"; continue; }
                            if (empty($course)) { $errors[] = "Row {$actualRowNum}: Course is required"; continue; }
                            if (empty($yearLevel)) { $errors[] = "Row {$actualRowNum}: Year level is required"; continue; }
                            if (empty($schoolYear)) { $errors[] = "Row {$actualRowNum}: School year is required"; continue; }

                            // Validate year level
                            if (!in_array($yearLevel, $validYearLevels)) {
                                $errors[] = "Row {$actualRowNum}: Invalid year level '{$yearLevel}' for {$firstName} {$lastName}. Must be one of: " . implode(', ', $validYearLevels);
                                continue;
                            }

                            // Validate school year format (YYYY-YYYY)
                            if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {
                                $errors[] = "Row {$actualRowNum}: Invalid school year format '{$schoolYear}' for {$firstName} {$lastName}. Format should be YYYY-YYYY (e.g., 2024-2025)";
                                continue;
                            }

                            // Validate email
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "Row {$actualRowNum}: Invalid email format '{$email}' for {$firstName} {$lastName}";
                                continue;
                            }

                            // Check for duplicate email in the same file
                            if (in_array(strtolower($email), $duplicateEmails)) {
                                $errors[] = "Row {$actualRowNum}: Duplicate email '{$email}' found in file";
                                continue;
                            }
                            $duplicateEmails[] = strtolower($email);

                            try {
                                $stmt->bind_param("sssssss",
                                    $firstName,
                                    $middleName,
                                    $lastName,
                                    $email,
                                    $course,
                                    $yearLevel,
                                    $schoolYear
                                );

                                if ($stmt->execute()) {
                                    $importCount++;
                                } else {
                                    if (strpos($stmt->error, 'email_unique') !== false || strpos($stmt->error, 'Duplicate entry') !== false) {
                                        $errors[] = "Row {$actualRowNum}: Email '{$email}' already exists in database for {$firstName} {$lastName}";
                                    } else {
                                        $errors[] = "Row {$actualRowNum}: Database error for {$firstName} {$lastName}: " . $stmt->error;
                                    }
                                }
                            } catch (Exception $e) {
                                $errors[] = "Row {$actualRowNum}: Error importing {$firstName} {$lastName}: " . $e->getMessage();
                            }
                        }

                        $stmt->close();
                    } else {
                        $error_message = "Invalid department selected: " . $department;
                    }
                    
                    // Create success/error message
                    if (isset($error_message)) {
                        // Already set above for unimplemented departments
                    } elseif ($importCount > 0) {
                        $success_message = "Successfully imported {$importCount} students to " . strtoupper($department) . " database.";
                        if (!empty($errors)) {
                            $errorCount = count($errors);
                            $success_message .= "\n\nHowever, {$errorCount} rows had errors:";
                            foreach (array_slice($errors, 0, 5) as $error) { // Show first 5 errors
                                $success_message .= "\n• " . $error;
                            }
                            if (count($errors) > 5) {
                                $success_message .= "\n• ... and " . (count($errors) - 5) . " more errors.";
                            }
                        }
                    } else {
                        $error_message = "No students were imported.";
                        if (!empty($errors)) {
                            $error_message .= " Errors encountered:";
                            foreach (array_slice($errors, 0, 5) as $error) { // Show first 5 errors
                                $error_message .= "\n• " . $error;
                            }
                            if (count($errors) > 5) {
                                $error_message .= "\n• ... and " . (count($errors) - 5) . " more errors.";
                            }
                        } else {
                            $error_message .= " Please check that your CSV file contains valid data.";
                        }
                    }
                    
                } catch (Exception $e) {
                    $error_message = "Error processing CSV file: " . $e->getMessage();
                } finally {
                    // Clean up uploaded file
                    if (file_exists($uploadPath)) {
                        unlink($uploadPath);
                    }
                }
            } else {
                $error_message = "Error uploading file. Please check file permissions and try again.";
            }
        } else {
            $error_message = "Invalid file format. Please upload CSV files only. To convert Excel to CSV: Open your Excel file → File → Save As → Choose 'CSV (Comma delimited)' format.";
        }
    } else {
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        );
        
        $error_message = "Upload error: " . ($upload_errors[$uploadedFile['error']] ?? 'Unknown error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Department Selection</title>
    <link rel="stylesheet" href="../styles/style.css">
    <script src="../scripts/script.js"></script>
    <style>
        .import-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .import-modal.show {
            display: flex;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            text-align: center;
            margin: 0;
            max-height: 90vh;
            overflow-y: auto;
        }

        .file-upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .file-upload-area:hover {
            border-color: #007bff;
        }

        .file-upload-area.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .upload-icon {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 10px;
        }

        .file-input {
            display: none;
        }

        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .import-btn {
            background: linear-gradient(135deg, #667eea 0%, #282bf0 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }

        .import-btn:hover {
            background: linear-gradient(135deg, #282bf0, #667eea 0% 100%);
            color: white;
        }

        .alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2000; /* sits above content but below back button */
            padding: 15px 24px;
            margin: 0;
            border-radius: 8px;
            text-align: center;
            white-space: pre-line;
            width: calc(100% - 40px);
            max-width: 900px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .selected-file {
            margin: 10px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }

        .conversion-help {
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            text-align: left;
        }

        .conversion-help h4 {
            color: #0056b3;
            margin: 0 0 10px 0;
        }

        .conversion-steps {
            margin: 10px 0;
        }

        .conversion-steps ol {
            margin: 5px 0 5px 20px;
            padding: 0;
        }

        .conversion-steps li {
            margin: 5px 0;
        }

        /* Ensure back button stays above alerts */
        .icon-back-btn {
            position: relative;
            z-index: 2100;
        }
    </style>
</head>
<body>
    <div class="icon-back-wrapper">
        <a href="<?php echo $cancelUrl; ?>" class="icon-back-btn" aria-label="Back">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
            
    <div class="index-page">
        <div class="header-section">      
            <h1>Nova Guard</h1>
            <h3>Student Violation Monitoring System</h3>
        </div>
        <div class="wrapper">
            <p>Please Select Student Department</p>
        </div>
        <div class="login-container">
            <div class="role-selection">
                <div style="text-decoration: none; color: inherit;">
                    <div class="role-option">
                        <img src="../assets/icons/images/jhs_icon.jpg" alt="Junior High School Icon">
                        <h3>Junior High School</h3>
                        <button class="import-btn" onclick="openImportModal('jhs'); event.preventDefault(); event.stopPropagation();">Import Students</button>
                    </div>
                </div>
                <div style="text-decoration: none; color: inherit;">
                    <div class="role-option">
                        <img src="../assets/icons/images/shs_icon.jpg" alt="Senior High School Icon">
                        <h3>Senior High School</h3>
                        <button class="import-btn" onclick="openImportModal('shs'); event.preventDefault(); event.stopPropagation();">Import Students</button>
                    </div>
                </div>
                <div style="text-decoration: none; color: inherit;">
                    <div class="role-option">
                        <img src="../assets/icons/images/college_icon.jpg" alt="College Icon">
                        <h3>College Department</h3>
                        <button class="import-btn" onclick="openImportModal('college'); event.preventDefault(); event.stopPropagation();">Import Students</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="import-modal">
        <div class="modal-content">
            <h3 id="modalTitle">Import Students</h3>
            
            <div class="conversion-help">
                <h4>📋 How to prepare your file:</h4>
                <div class="conversion-steps">
                    <ol>
                        <li>Open your Excel file</li>
                        <li>Go to <strong>File → Save As</strong></li>
                        <li>Choose <strong>CSV (Comma delimited) (*.csv)</strong></li>
                        <li>Save the file</li>
                        <li>Upload the saved CSV file below</li>
                    </ol>
                </div>
                <p><strong>Required columns (in order):</strong><br>
                <span id="requiredColumns">id, firstName, middleName, lastName, email, year_lvl, school_year</span></p>
                <p id="deptNotes"><strong>For JHS:</strong> year_lvl must be Grade7, Grade8, Grade9, or Grade10<br>
                <strong>School year format:</strong> 2024-2025</p>
            </div>
            
            <form id="importForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="department" name="department" value="">
                
                <div class="file-upload-area" onclick="document.getElementById('fileInput').click();" 
                     ondrop="dropHandler(event);" ondragover="dragOverHandler(event);" ondragleave="dragLeaveHandler(event);">
                    <div class="upload-icon">📄</div>
                    <p>Click to select CSV file or drag and drop</p>
                    <p><small><strong>Only CSV files are supported</strong></small></p>
                </div>
                
                <input type="file" id="fileInput" name="csv_file" class="file-input" 
                       accept=".csv" onchange="fileSelected(event);">
                
                <div id="selectedFile" class="selected-file" style="display: none;">
                    <p><strong>Selected file:</strong> <span id="fileName"></span></p>
                    <p><strong>Size:</strong> <span id="fileSize"></span></p>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>Upload CSV File</button>
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal();">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openImportModal(dept) {
            document.getElementById('department').value = dept;
            document.getElementById('modalTitle').textContent = 'Import ' + dept.toUpperCase() + ' Students';

            // Update instructions dynamically based on department
            const colsEl = document.getElementById('requiredColumns');
            const notesEl = document.getElementById('deptNotes');
            if (colsEl && notesEl) {
                if (dept === 'shs') {
                    colsEl.textContent = 'id, firstName, middleName, lastName, email, strand, year_lvl, school_year';
                    notesEl.innerHTML = '<strong>For SHS:</strong> strand must be STEM, HUMSS, ABM, or TVL; year_lvl must be Grade11 or Grade12<br><strong>School year format:</strong> 2024-2025';
                } else if (dept === 'jhs') {
                    colsEl.textContent = 'id, firstName, middleName, lastName, email, year_lvl, school_year';
                    notesEl.innerHTML = '<strong>For JHS:</strong> year_lvl must be Grade7, Grade8, Grade9, or Grade10<br><strong>School year format:</strong> 2024-2025';
                } else if (dept === 'college') {
                    colsEl.textContent = 'id, firstName, middleName, lastName, email, course, year_lvl, school_year';
                    notesEl.innerHTML = '<strong>For College:</strong> course must match your program codes (e.g., BTVTEd, BSCRIM, BSIS, BSEntrep, BSAI); year_lvl must be 1stYear, 2ndYear, 3rdYear, or 4thYear<br><strong>School year format:</strong> 2024-2025';
                } else {
                    colsEl.textContent = 'id, firstName, middleName, lastName, email, school_year';
                    notesEl.innerHTML = '<strong>Department-specific rules:</strong> Will be shown here when implemented.';
                }
            }

            const modal = document.getElementById('importModal');
            modal.classList.add('show');
        }

        function closeImportModal() {
            const modal = document.getElementById('importModal');
            modal.classList.remove('show');
            document.getElementById('importForm').reset();
            document.getElementById('selectedFile').style.display = 'none';
            document.getElementById('uploadBtn').disabled = true;
        }

        function fileSelected(event) {
            const file = event.target.files[0];
            if (file) {
                // Check if it's a CSV file
                if (file.type !== 'text/csv' && !file.name.toLowerCase().endsWith('.csv')) {
                    alert('Please select a CSV file only. To convert Excel to CSV:\n1. Open Excel file\n2. File → Save As\n3. Choose CSV (Comma delimited)\n4. Save and upload the CSV file');
                    event.target.value = '';
                    return;
                }
                
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                document.getElementById('selectedFile').style.display = 'block';
                document.getElementById('uploadBtn').disabled = false;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Drag and drop functionality
        function dragOverHandler(ev) {
            ev.preventDefault();
            ev.currentTarget.classList.add('dragover');
        }

        function dragLeaveHandler(ev) {
            ev.currentTarget.classList.remove('dragover');
        }

        function dropHandler(ev) {
            ev.preventDefault();
            ev.currentTarget.classList.remove('dragover');
            
            if (ev.dataTransfer.items) {
                for (let i = 0; i < ev.dataTransfer.items.length; i++) {
                    if (ev.dataTransfer.items[i].kind === 'file') {
                        const file = ev.dataTransfer.items[i].getAsFile();
                        if (file && isValidFileType(file)) {
                            document.getElementById('fileInput').files = ev.dataTransfer.files;
                            fileSelected({target: {files: [file]}});
                            break;
                        } else {
                            alert('Please drop a CSV file only.');
                        }
                    }
                }
            }
        }

        function isValidFileType(file) {
            return file.type === 'text/csv' || file.name.toLowerCase().endsWith('.csv');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('importModal');
            if (event.target == modal) {
                closeImportModal();
            }
        }
    </script>
</body>
</html>