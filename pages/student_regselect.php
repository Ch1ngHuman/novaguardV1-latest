<?php
session_start();
$cancelUrl = '../pages/main-menu.html';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $cancelUrl = 'admin-dashboard.php';
    } elseif ($_SESSION['role'] === 'guard') {
        $cancelUrl = 'guard-dashboard.php';
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

</head>
<body>
    <div class="icon-back-wrapper">
        <a href="<?php echo $cancelUrl; ?>" class="icon-back-btn" aria-label="Back">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
            
    <div class="index-page">
        <div class="header-section">      
            <h1>Student Violation Monitoring System</h1>
        </div>
        <div class="wrapper">
            <p>Please Select Student Department</p>
        </div>
        <div class="login-container">
            <div class="role-selection">
                <a href="jhs-list.php" style="text-decoration: none; color: inherit;">
                    <div class="role-option">
                        <img src="../assets/icons/images/jhs_icon.jpg" alt="Junior High School Icon">
                        <h3>Junior High School</h3>
                        <p>Grade 7-10</p>
                    </div>
                </a>
                <a href="shs-list.php" style="text-decoration: none; color: inherit;">
                    <div class="role-option">
                        <img src="../assets/icons/images/shs_icon.jpg" alt="Senior High School Icon">
                        <h3>Senior High School</h3>
                        <p>Grade 11-12</p>
                    </div>
                </a>
                <a href="college-list.php" style="text-decoration: none; color: inherit;">
                    <div class="role-option">
                        <img src="../assets/icons/images/college_icon.jpg" alt="College Icon">
                        <h3>College Department</h3>
                        <p>Year 1st-4th</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>