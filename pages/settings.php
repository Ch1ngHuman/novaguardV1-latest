<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/style.css">
    <title>Settings - Nova Guard</title>
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .settings-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .settings-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }

    </style>
</head>
<body>
    <div class="settings-container">
        <div class="icon-back-wrapper">
            <a href="admin-dashboard.php" class="icon-back-btn" aria-label="Back to Dashboard">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
        
        <div class="settings-header">
            <h1>Settings</h1>
        </div>

        <div class="settings-grid">
            <div class="menu-card">
                <h3>School Year</h3>
                <p>Manage active school year and semester records</p>
                <a href="manage-school-year.php" class="admin-button">Manage School Year</a>
            </div>

            <div class="menu-card">
                <h3>Generate Reports</h3>
                <p>View monitoring reports</p>
                <a href="generate_reports.php" class="admin-button">View Reports</a>
            </div>

            <div class="menu-card">
                <h3>Import list</h3>
                <p>Add student list for each department</p>
                <a href="import-list.php" class="admin-button">Import</a>
            </div>
        </div>
    </div>
</body>
</html>