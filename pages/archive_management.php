<?php
session_start();
require_once '../connect.php';
require_once 'archive_functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$message_type = '';

// Handle archive action
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'archive') {
    $school_year_id = intval($_POST['school_year_id']);
    
    if ($school_year_id > 0) {
        // Check if already archived
        if (isSchoolYearArchived($conn, $school_year_id)) {
            $message = "This school year has already been archived.";
            $message_type = "warning";
        } else {
            $result = archiveSchoolYearData($conn, $school_year_id);
            
            if ($result['success']) {
                $message = "Successfully archived school year data. ";
                $message_type = "success";
            } else {
                $message = "Error archiving data: " . $result['error'];
                $message_type = "error";
            }
        }
    }
}

// Get archivable school years
$archivable_years = getArchivableSchoolYears($conn);

// Get all school years with archive status
$all_years_query = "SELECT sy.id, sy.school_year, sy.is_current,
                    CASE WHEN vha.school_year_id IS NOT NULL THEN 1 ELSE 0 END as is_archived
                    FROM school_year sy
                    LEFT JOIN (SELECT DISTINCT school_year_id FROM violation_history_archive) vha 
                        ON sy.id = vha.school_year_id
                    ORDER BY sy.school_year DESC";
$all_years_result = $conn->query($all_years_query);
$all_years = $all_years_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Management - NovaGuard</title>
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        .archive-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        .archive-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        .archive-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .archive-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .archive-btn:hover {
            background: #c82333;
        }
        .archive-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .back-button {
            background: #819A91;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-button:hover {
            background: #6d8a7a;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .years-table {
            width: 100%;
            border-collapse: collapse;
        }
        .years-table th,
        .years-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .years-table th {
            background: #667eea;
            color: white;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-current {
            background: #28a745;
            color: white;
        }
        .status-archived {
            background: #6c757d;
            color: white;
        }
        .status-active {
            background: #ffc107;
            color: #212529;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .warning-box strong {
            display: block;
            margin-bottom: 10px;
        }


        /* Confirmation Modal Styles */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .confirmation-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .confirmation-message {
            font-size: 1.2em;
            margin-bottom: 25px;
            color: #333;
            line-height: 1.5;
        }

        .confirmation-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm-yes {
            background: #dc3545;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
        }

        .btn-confirm-yes:hover {
            background: #c82333;
        }

        .btn-confirm-no {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
        }

        .btn-confirm-no:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-message">
                Are you sure you want to archive this school year? This action cannot be undone.
            </div>
            <div class="confirmation-buttons">
                <button class="btn-confirm-yes" id="confirmYes">Yes, Archive</button>
                <button class="btn-confirm-no" id="confirmNo">Cancel</button>
            </div>
        </div>
    </div>

    <div class="archive-container">
        <div class="icon-back-wrapper">
            <a href="settings.php" class="icon-back-btn" aria-label="Back to Dashboard">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>

        <h1>Archive Management</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Archive Form -->
        <?php if (!empty($archivable_years)): ?>
            <div class="archive-card">
                <h2 style="color: #000000;">Archive School Year Data</h2>
                
                <div class="warning-box">
                    <strong>Warning:</strong>
                    Archiving will copy violation data to permanent storage for historical reporting. 
                    This action cannot be undone.
                </div>
                
                <form method="POST" class="archive-form" id="archiveForm" onsubmit="return showArchiveConfirmation(event);">
                    <input type="hidden" name="action" value="archive">
                    
                    <div class="form-group">
                        <label for="school_year_id">Select School Year to Archive:</label>
                        <select name="school_year_id" id="school_year_id" required>
                            <option value="">-- Select School Year --</option>
                            <?php foreach ($archivable_years as $year): ?>
                                <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['school_year']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="archive-btn">Archive Selected School Year</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- School Years Status -->
        <div class="archive-card">
            <h2 style="color: #000000;">School Years Status</h2>
            <table class="years-table">
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th>Status</th>
                        <th>Archive Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_years as $year): ?>
                        <tr>
                            <td><?= htmlspecialchars($year['school_year']) ?></td>
                            <td>
                                <?php if ($year['is_current']): ?>
                                    <span class="status-badge status-current">Current</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Past</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($year['is_archived']): ?>
                                    <span class="status-badge status-archived">Archived</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Not Archived</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($archivable_years)): ?>
            <div class="archive-card">
                <h3>No School Years Available for Archiving</h3>
                <p>All past school years have already been archived, or there are no past school years to archive.</p>
                <p>You can view archived data in the <a href="generate_reports.php">Generate Reports</a> section.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showArchiveConfirmation(event) {
            event.preventDefault(); // Prevent form submission

            const modal = document.getElementById('confirmationModal');
            const confirmYes = document.getElementById('confirmYes');
            const confirmNo = document.getElementById('confirmNo');
            const form = document.getElementById('archiveForm');

            // Show modal
            modal.style.display = 'flex';

            // Handle Yes button click
            confirmYes.onclick = function() {
                modal.style.display = 'none';
                // Submit the form
                form.onsubmit = null; // Remove the event handler to avoid infinite loop
                form.submit();
            };

            // Handle No button click
            confirmNo.onclick = function() {
                modal.style.display = 'none';
            };

            // Close modal when clicking outside
            modal.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            };

            return false; // Prevent form submission
        }
    </script>
</body>
</html>
