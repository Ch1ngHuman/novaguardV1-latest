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
// Include your database connection file
require_once '../connect.php';
require_once '../utils/notifications.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and sanitize
    $firstName = $_POST['studentFirstName'];
    $middleName = $_POST['studentMiddleName'];
    $lastName = $_POST['studentLastName'];
    $email = $_POST['email'];
    $year_lvl = $_POST['student_yearlvl'];

    // Get current school year
    $school_year_query = "SELECT school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
    $school_year_result = $conn->query($school_year_query);
    $current_school_year_data = $school_year_result->fetch_assoc();
    $current_school_year = $current_school_year_data['school_year'] ?? '2025-2026';

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM jhs_students WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $errorMessage = 'Email is already registered!';
    } else {
        // Prepare and execute the insert statement
        $stmt = $conn->prepare("INSERT INTO jhs_students (firstName, middleName, lastName, email, year_lvl, school_year) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $firstName, $middleName, $lastName, $email, $year_lvl, $current_school_year);

        if ($stmt->execute()) {
            $newStudentId = $conn->insert_id;
            // Log notification
            $fullName = trim($firstName . ' ' . $lastName);
            logNotification($conn, 'student_registered', 'JHS student <strong>' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</strong> has been registered.');
            header("Location: violation-list-jhs.php?student_id=$newStudentId&student_type=jhs&registered=1");
            exit();
        } else {
            $errorMessage = 'Error: ' . $stmt->error;
        }
        $stmt->close();
    }
    $checkStmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Student</title>
    <style>

    </style>
    <link rel="stylesheet" href="../styles/style.css">
    <script type="text/javascript" src="../js/script.js" defer></script>
    <style>
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
    .modal { background: #ffffff; max-width: 520px; width: 92%; border-radius: 8px; padding: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
    .modal h2 { margin-top: 0; }
    .modal .actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
    .modal .actions button { padding: 8px 12px; }
    </style>
    </head>
<body>
    <div class="icon-back-wrapper top20">
        <a href="jhs-list.php" class="icon-back-btn purple" aria-label="Back to Dashboard">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>

    <div>      
        <h1>Register JHS Student</h1>
        <?php if (!empty($errorMessage)): ?>
            <div class="notification error"><?php echo $errorMessage; ?></div>
        <?php endif; ?>
    </div>
    <div class="form-box">
        <form id="form" class="globalForm" method="POST" action="">
            <div>
                <label for="inputStudentFirstName"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Z"/></svg></label>
                <input type="text" name="studentFirstName" id="inputStudentFirstName" placeholder="First name" required>
            </div>
                        <div>
                <label for="inputStudentMiddleName"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Z"/></svg></label>
                <input type="text" name="studentMiddleName" id="inputStudentMiddleName" placeholder="Middle name" required>
            </div>
                        <div>
                <label for="inputStudentLastName"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Z"/></svg></label>
                <input type="text" name="studentLastName" id="inputStudentLastName" placeholder="Last name" required>
            </div>
            <div class="wrapper">
            <p>Please make sure to use the school provided email</p>
            </div>
            <div>
                <label for="inputEmail">
                    <span>@</span>
                </label>
                <input type="email" name="email" id="inputEmail" placeholder="Email" required>
            </div>
            <div>
                <label for="inputYear"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#5f6368"><path d="M400-400h160v-80H400v80Zm0-120h320v-80H400v80Zm0-120h320v-80H400v80Zm-80 400q-33 0-56.5-23.5T240-320v-480q0-33 23.5-56.5T320-880h480q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H320ZM160-80q-33 0-56.5-23.5T80-160v-560h80v560h560v80H160Z"/></svg></label>
                <select name="student_yearlvl" id="student_yearlvl" required>
                    <option value="" disabled selected>Select Year Level</option>
                    <option value="grade7">Grade 7</option>
                    <option value="grade8">Grade 8</option>
                    <option value="grade9">Grade 9</option>
                    <option value="grade10">Grade 10</option>
                </select>
            </div>
            <button type="submit">Register Student</button>
        </form>
    </div>
</body>

<div id="confirmBackdrop" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="modal">
        <h2 id="confirmTitle">Confirm details</h2>
        <p><strong>Please verify the information before submitting:</strong></p>
        <ul id="confirmList"></ul>
        <div class="actions">
            <button type="button" id="confirmCancelBtn" class="confirmCancelBtn">Cancel</button>
            <button type="button" id="confirmSubmitBtn" class="confirmSubmitBtn">Submit</button>
        </div>
    </div>
    </div>

<script>
(function(){
    var form = document.getElementById('form');
    var backdrop = document.getElementById('confirmBackdrop');
    var list = document.getElementById('confirmList');
    var cancelBtn = document.getElementById('confirmCancelBtn');
    var submitBtn = document.getElementById('confirmSubmitBtn');
    var hasConfirmed = false;
    var cancelRedirect = 'jhs_register.php';

    function escapeHTML(s){
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function show(items){
        list.innerHTML = '';
        items.forEach(function(item){
            var li = document.createElement('li');
            li.innerHTML = item.label + ': <strong>' + escapeHTML(item.value) + '</strong>';
            list.appendChild(li);
        });
        backdrop.style.display = 'flex';
    }
    function hide(){ backdrop.style.display = 'none'; }

    form.addEventListener('submit', function(e){
        if (hasConfirmed) { return; }
        e.preventDefault();
        var yearSelect = document.getElementById('student_yearlvl');
        var items = [
            { label: 'First name', value: document.getElementById('inputStudentFirstName').value },
            { label: 'Middle name', value: document.getElementById('inputStudentMiddleName').value },
            { label: 'Last name', value: document.getElementById('inputStudentLastName').value },
            { label: 'Email', value: document.getElementById('inputEmail').value },
            { label: 'Year level', value: yearSelect.options[yearSelect.selectedIndex] ? yearSelect.options[yearSelect.selectedIndex].text : '' }
        ];
        show(items);
    });

    cancelBtn.addEventListener('click', function(){
        window.location.href = cancelRedirect;
    });
    submitBtn.addEventListener('click', function(){
        hasConfirmed = true;
        hide();
        form.submit();
    });
})();
</script>

</html>