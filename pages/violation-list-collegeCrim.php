<?php
session_start();
require_once '../connect.php';
require_once '../utils/notifications.php';

// Get student information from URL parameters
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student_type = isset($_GET['student_type']) ? $_GET['student_type'] : '';
$just_registered = isset($_GET['registered']) && $_GET['registered'] === '1';

// Get student name if just registered
$student_name = '';
if ($student_id > 0) {
    $table_name = 'college_students';
    $name_query = "SELECT CONCAT(firstName, ' ', lastName) as full_name FROM $table_name WHERE id = ?";
    $stmt = $conn->prepare($name_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $student_name = $row['full_name'];
    }
    $stmt->close();
}

// Get current school year
$school_year_query = "SELECT id FROM school_year WHERE is_current = TRUE LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year = $school_year_result->fetch_assoc();
$school_year_id = $current_school_year['id'] ?? null;


    // Get school year text for archiving
    $school_year_text = '';
    if ($school_year_id) {
        $sy_stmt = $conn->prepare("SELECT school_year FROM school_year WHERE id = ? LIMIT 1");
        if ($sy_stmt) {
            $sy_stmt->bind_param('i', $school_year_id);
            $sy_stmt->execute();
            $sy_res = $sy_stmt->get_result();
            if ($sy_row = $sy_res->fetch_assoc()) {
                $school_year_text = $sy_row['school_year'];
            }
            $sy_stmt->close();
        }
    }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $violations = $_POST['violations'] ?? [];

    // Get student info from POST data (from hidden fields)
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : $student_id;
    $student_type = isset($_POST['student_type']) ? (string)$_POST['student_type'] : $student_type;

    // Ensure all variables are the correct type
    $student_id = (int)$student_id;
    $student_type = (string)$student_type;
    $school_year_id = $school_year_id ? (int)$school_year_id : 1; // Default to 1 if null

    if (!empty($violations)) {
        // Auto-assign sanctions based on violation type - CALCULATE BEFORE INSERTING VIOLATIONS
        // Calculate total sanction hours based on each violation
        $major_violations = ['Vape', 'Fire Alarm'];
        $sanction_hours = 0;
        $major_violation_count = 0;
        $regular_violation_count = 0;
        
        // Check if student has existing violations (BEFORE adding new ones)
        $check_existing_violations_stmt = $conn->prepare("SELECT COUNT(*) as existing_count FROM college_crim_violations WHERE student_id = ? AND school_year_id = ?");
        $check_existing_violations_stmt->bind_param("ii", $student_id, $school_year_id);
        $check_existing_violations_stmt->execute();
        $existing_violations_result = $check_existing_violations_stmt->get_result()->fetch_assoc();
        $has_existing_violations = $existing_violations_result['existing_count'] > 0;
        $check_existing_violations_stmt->close();
        
        foreach ($violations as $violation) {
            if (in_array($violation, $major_violations)) {
                // 8 hours for each major violation
                $sanction_hours += 8;
                $major_violation_count++;
                error_log("Major violation detected: $violation - adding 8 hours");
            } else {
                // 3 hours for minor violations only if student has no existing violations
                if (!$has_existing_violations) {
                    $sanction_hours += 3;
                    error_log("Minor violation detected: $violation - adding 3 hours (first violation)");
                } else {
                    error_log("Minor violation detected: $violation - no automatic sanction hours (student has existing violations)");
                }
                $regular_violation_count++;
            }
        }
        
        $minor_hours = $has_existing_violations ? 0 : ($regular_violation_count * 3);
        error_log("Sanction calculation: $major_violation_count major violations ($major_violation_count x 8 = " . ($major_violation_count * 8) . " hours) + $regular_violation_count minor violations (" . ($has_existing_violations ? "no automatic hours - student has existing violations" : "3 hours each - first violation") . ") = $sanction_hours total hours");
        
        // Calculate total sanction hours
        $total_sanction_hours = $sanction_hours;

        // Get current semester
        $current_semester = getCurrentSemester($conn);

        // Updated SQL to match your actual database columns
        $stmt = $conn->prepare("INSERT INTO college_crim_violations (student_id, violation_type, description, school_year_id, semester_id, date_recorded) VALUES (?, ?, ?, ?, ?, NOW())");

        if ($stmt) {
            foreach ($violations as $violation) {
                $violation = (string)$violation; // Ensure violation is string
                // Use violation as both type and description for now
                $stmt->bind_param("issii", $student_id, $violation, $violation, $school_year_id, $current_semester);

                if (!$stmt->execute()) {
                    // Log any execution errors
                    error_log("SQL Error: " . $stmt->error);
                } else {
                    error_log("Successfully inserted violation: " . $violation . " for student: " . $student_id);
                    // Auto-archive just this inserted violation and upsert summary
                        $archiveSql = "INSERT INTO violation_history_archive
                        (student_id, student_name, student_type, student_level, student_course_strand, violation_type,
                        violation_description, date_recorded, school_year_id, semester_id, school_year, sanction_hours)
                        SELECT s.id,
                            CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.LastName),
                            'college', s.year_lvl, s.course, ?, ?, NOW(), ?, ?, ?, 
                            COALESCE(san.sanction_hours, 0)
                        FROM college_students s
                        LEFT JOIN student_sanctions san ON san.student_id=s.id AND san.student_type='college' AND san.school_year_id=?
                        WHERE s.id=?";
                    if ($arc = $conn->prepare($archiveSql)) {
                        // Fixed parameter binding order: violation, violation, school_year_id, current_semester, school_year_text, school_year_id, student_id
                        $arc->bind_param('ssiisii', $violation, $violation, $school_year_id, $current_semester, $school_year_text, $school_year_id, $student_id);
                        $arc->execute();
                        $arc->close();
                    }
                    $sumSql = "INSERT INTO student_summary_archive
                        (student_id, student_name, student_type, student_level, student_course_strand, school_year_id, semester_id, school_year,
                        total_violations, total_sanction_hours, first_violation_date)
                        SELECT s.id, CONCAT(s.firstName,' ',COALESCE(s.middleName,''),' ',s.LastName),
                            'college', s.year_lvl, s.course, ?, ?, ?, 1,
                            COALESCE(san.sanction_hours,0), NOW()
                        FROM college_students s
                        LEFT JOIN student_sanctions san ON san.student_id=s.id AND san.student_type='college' AND san.school_year_id=?
                        WHERE s.id=?
                        ON DUPLICATE KEY UPDATE
                        total_violations = total_violations + 1,
                        total_sanction_hours = VALUES(total_sanction_hours),
                        first_violation_date = LEAST(first_violation_date, VALUES(first_violation_date))";
                    if ($sum = $conn->prepare($sumSql)) {
                        // Fixed parameter binding order: school_year_id, current_semester, school_year_text, school_year_id, student_id
                        $sum->bind_param('iisii', $school_year_id, $current_semester, $school_year_text, $school_year_id, $student_id);
                        $sum->execute();
                        $sum->close();
                    }
                }
            }


            // Sanction calculation already done above before violation insertion
            
            // Check if student already has active sanctions
            $check_sanction_stmt = $conn->prepare("SELECT sanction_hours FROM student_sanctions WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'");
            $check_sanction_stmt->bind_param("isi", $student_id, $student_type, $school_year_id);
            $check_sanction_stmt->execute();
            $existing_sanction = $check_sanction_stmt->get_result()->fetch_assoc();
            $check_sanction_stmt->close();

            if ($existing_sanction) {
                // Update existing sanction by adding new hours
                $current_hours = (int)$existing_sanction['sanction_hours'];
                $new_total_hours = $current_hours + $total_sanction_hours;
                
                $update_sanction_stmt = $conn->prepare("UPDATE student_sanctions SET sanction_hours = ?, date_assigned = NOW(), semester_id = ? WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'");
                $update_sanction_stmt->bind_param("isiii", $new_total_hours, $current_semester, $student_id, $student_type, $school_year_id);
                
                if ($update_sanction_stmt->execute()) {
                    $violation_summary = "";
                    if ($major_violation_count > 0) {
                        $violation_summary .= "$major_violation_count major violation(s) (Vape/Fire Alarm) ";
                    }
                    if ($regular_violation_count > 0) {
                        $violation_summary .= "$regular_violation_count regular violation(s)";
                    }
                    error_log("Successfully updated sanction for student: " . $student_id . " - Added {$total_sanction_hours} hours (was {$current_hours}, now {$new_total_hours}) for " . $violation_summary);
                } else {
                    error_log("Failed to update sanction: " . $update_sanction_stmt->error);
                }
                $update_sanction_stmt->close();
            } else {
                // Insert new sanction
                $insert_sanction_stmt = $conn->prepare("INSERT INTO student_sanctions (student_id, student_type, sanction_hours, date_assigned, school_year_id, semester_id, status) VALUES (?, ?, ?, NOW(), ?, ?, 'active')");
                $insert_sanction_stmt->bind_param("isiii", $student_id, $student_type, $total_sanction_hours, $school_year_id, $current_semester);
                
                if ($insert_sanction_stmt->execute()) {
                    $violation_summary = "";
                    if ($major_violation_count > 0) {
                        $violation_summary .= "$major_violation_count major violation(s) (Vape/Fire Alarm) ";
                    }
                    if ($regular_violation_count > 0) {
                        $violation_summary .= "$regular_violation_count regular violation(s)";
                    }
                    error_log("Successfully assigned new {$total_sanction_hours}-hour sanction to student: " . $student_id . " for " . $violation_summary);
                } else {
                    error_log("Failed to insert sanction: " . $insert_sanction_stmt->error);
                }
                $insert_sanction_stmt->close();
            }

                // Only increment archive when sanction hours were added (do not decrease on reductions)
                if (!empty($total_sanction_hours) && (int)$total_sanction_hours > 0) {
                    try {
                        $inc = $conn->prepare("UPDATE student_summary_archive SET total_sanction_hours = total_sanction_hours + ? WHERE student_id = ? AND student_type = ? AND school_year_id = ?");
                        if ($inc) {
                            $inc->bind_param('iisi', $total_sanction_hours, $student_id, $student_type, $school_year_id);
                            $inc->execute();
                            $inc->close();
                        }
                    } catch (Exception $e) {
                        error_log('Failed to increment student_summary_archive: ' . $e->getMessage());
                    }
                }
        } else {
            error_log("Failed to prepare statement: " . $conn->error);
        }

        // Log notification for violation added
        if ($student_id > 0) {
            $who = $student_name !== '' ? $student_name : ('Student #' . $student_id);
            $whoSafe = htmlspecialchars($who, ENT_QUOTES, 'UTF-8');
            logNotification($conn, 'violation_added', '<strong>' . $whoSafe . '</strong> received new violation(s).');
        }

        // Redirect back to student records with success message
        header("Location: college-list.php?success=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College CRIM Violation Selection</title>
    <link rel="stylesheet" href="../styles/style.css">
    <script src="../scripts/script.js"></script>
</head>
<body>
    <div class="icon-back-wrapper">
        <a href="college-list.php" class="icon-back-btn" aria-label="Back to Records">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>

     <style>
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        @keyframes shake {
            0%, 20%, 40%, 60%, 80% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        }

        .content-wrapper {
            padding-top: 40px;
            transition: margin-top 0.5s ease-out;
        }

        .with-notification {
            margin-top: 0;
        }

        .notification-hidden {
            margin-top: -100px;
        }

        .error-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #f56565;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
            text-align: center;
            font-size: 1.1em;
            z-index: 1001;
            animation: fadeOut 5s forwards;
            display: none;
        }

        .error-notification.show {
            display: block;
        }
    </style>

    <!-- Error Notification -->
    <div id="errorNotification" class="error-notification">
        <strong>Please select at least one violation!</strong>
    </div>

    <?php if ($just_registered && !empty($student_name)): ?>
    <div id="successNotification" style="
        position: relative;
        margin: 20px auto;
        width: fit-content;
        background-color: #4CAF50;
        color: white;
        padding: 15px 30px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
        animation: fadeOut 5s forwards;
        font-size: 1.1em;
    ">
        Student <strong><?php echo htmlspecialchars($student_name); ?></strong> has been registered successfully!
    </div>
    <?php endif; ?>

    <h3>Please Select Violation/s</h3>
    <form method="POST" id="violationForm">
        <!-- Hidden fields to preserve student information -->
        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
        <input type="hidden" name="student_type" value="<?php echo htmlspecialchars($student_type); ?>">

        <div class="violation-options">
            <div class="violation-card">
                <h4>Improper Type A Uniform</h4>
                <ul>
                    <li>Not wearing Type A top</li>
                    <li>Not wearing appropriate shoes</li>
                    <li>Wearing of white socks</li>
                    <li>No ID</li>
                </ul>
                <input type="checkbox" name="violations[]" value="Improper Type A Uniform" id="improper_typeA_uniform" class="violation-checkbox">
                <label for="improper_typeA_uniform" class="violation-label">Select Violation</label>
            </div>

            <div class="violation-card">
                <h4>Improper Type B Uniform</h4>
                <ul>
                    <li>Not wearing appropriate shoes</li>
                    <li>Not wearing appropriate pants</li>
                    <li>Not wearing appropriate top/shirt (black shirt)</li>
                    <li>No ID</li>
                </ul>
                <input type="checkbox" name="violations[]" value="Improper Type B Uniform" id="improper_TypeB_uniform" class="violation-checkbox">
                <label for="improper_TypeB_uniform" class="violation-label">Select Violation</label>
            </div>

            <div class="violation-card">
                <h4>Not Wearing of Course Shirt</h4>
                <ul>
                    <li>Not wearing the designated course shirt</li>
                    <li>No ID</li>
                </ul>
                <input type="checkbox" name="violations[]" value="Not Wearing of Course Shirt" id="not-wearing-courseShirt" class="violation-checkbox">
                <label for="not-wearing-courseShirt" class="violation-label">Select Violation</label>
            </div>

            <div class="violation-card">
                <h4>Earrings (Boys)</h4>
                <p>Wearing of earrings</p>
                <input type="checkbox" name="violations[]" value="Earrings (Boys)" id="earrings_boys" class="violation-checkbox">
                <label for="earrings_boys" class="violation-label">Select Violation</label>
            </div>

            <div class="violation-card">
                <h4>Piercings (Girls)</h4>
                <p>Multiple ear piercings</p>
                <input type="checkbox" name="violations[]" value="Piercings (Girls)" id="piercings_girls" class="violation-checkbox">
                <label for="piercings_girls" class="violation-label">Select Violation</label>
            </div>

            <div class="violation-card">
                <h4>Late</h4>
                <p>Violation for tardiness</p>
                <input type="checkbox" name="violations[]" value="Late" id="late" class="violation-checkbox">
                <label for="late" class="violation-label">Select Violation</label>
            </div>

            <div class="major-violation-card">
                <h4>Vape</h4>
                <p>Violation for possession or vaping on campus</p>
                <input type="checkbox" name="violations[]" value="Vape" id="vape" class="violation-checkbox">
                <label for="vape" class="violation-label">Select Violation</label>
            </div>

            <div class="major-violation-card">
                <h4>Fire Alarm</h4>
                <p>Fire alarm tampering or misuse</p>
                <input type="checkbox" name="violations[]" value="Fire Alarm" id="fire_alarm" class="violation-checkbox">
                <label for="fire_alarm" class="violation-label">Select Violation</label>
            </div>
        </div>
        <div class="submit-container">
            <button type="button" class="submit-btn" onclick="submitViolations()">Submit</button>
        </div>
    </form>

    <style>
        .submit-container {
            position: fixed;
            left: 50%;
            bottom: 24px; /* space from bottom */
            transform: translateX(-50%);
            z-index: 1000;
            border-radius: 8px;
            padding: 10px 24px;
            display: flex;
            justify-content: center;
            width: fit-content;
        }
        .submit-btn {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .submit-btn:hover {
            background-color: #5a6fd6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .submit-btn.error {
            animation: shake 0.5s ease-in-out;
        }
        .violation-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: auto auto;
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .violation-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            top: 0;
        }
        .violation-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }
        .major-violation-card {
            background:rgb(41, 41, 223);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            top: 0;
            h4 {
                color: white;
                margin-bottom: 10px;
            }
            p {
                color: whitesmoke;
                margin-bottom: 15px;
            }
            .violation-checkbox {
            display: none;
            }
            .violation-label {
                display: inline-flex;
                align-items: center;
                cursor: pointer;
                margin-top: 10px;
                color: whitesmoke;
                font-size: 1.1em;
                transition: color 0.3s;
            }
            .violation-label:before {
                content: '';
                width: 24px;
                height: 24px;
                border: 2px solid whitesmoke;
                border-radius: 4px;
                margin-right: 10px;
                transition: all 0.3s;
            }
            .violation-checkbox:checked + .violation-label:before {
                background-color: #ff4757;
                border-color: #ff4757;
                content: '✓';
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }
            .violation-label:hover {
                color: #ff4757;
            }
        }
        .major-violation-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }    
        .violation-checkbox {
            display: none;
        }
        .violation-label {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            margin-top: 10px;
            color: #4a5568;
            font-size: 1.1em;
            transition: color 0.3s;
        }
        .violation-label:before {
            content: '';
            width: 24px;
            height: 24px;
            border: 2px solid #667eea;
            border-radius: 4px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        .violation-checkbox:checked + .violation-label:before {
            background-color: #667eea;
            border-color: #667eea;
            content: '✓';
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .violation-label:hover {
            color: #667eea;
        }
        h4 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        p {
            color: #4a5568;
            margin-bottom: 15px;
        }
    </style>

    <script>
        // Remove the contentWrapper references since they don't exist
        if (document.getElementById('successNotification')) {
            setTimeout(() => {
                const notification = document.getElementById('successNotification');
                if (notification) {
                    notification.style.display = 'none';
                }
            }, 5000);
        }

        function showErrorNotification() {
            const errorNotification = document.getElementById('errorNotification');
            errorNotification.classList.add('show');

            // Auto-hide after 4 seconds
            setTimeout(() => {
                hideErrorNotification();
            }, 4000);
        }

        function hideErrorNotification() {
            const errorNotification = document.getElementById('errorNotification');
            errorNotification.classList.remove('show');
        }

        function escapeHTML(s){
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function submitViolations() {
            const form = document.getElementById('violationForm');
            const checkedViolations = form.querySelectorAll('input[name="violations[]"]:checked');
            const submitBtn = document.querySelector('.submit-btn');

                if (checkedViolations.length === 0) {
                showErrorNotification();
                submitBtn.classList.add('error');
                setTimeout(() => { submitBtn.classList.remove('error'); }, 500);
                return;
            }

            const list = document.getElementById('confirmList');
            list.innerHTML = '';
            const studentName = "<?php echo htmlspecialchars($student_name); ?>";
            const nameLi = document.createElement('li');
            nameLi.innerHTML = 'Student: <strong>' + escapeHTML(studentName) + '</strong>';
            list.appendChild(nameLi);

            checkedViolations.forEach(function(v){
                const li = document.createElement('li');
                li.innerHTML = 'Violation: <strong>' + escapeHTML(v.value) + '</strong>';
                list.appendChild(li);
            });

            document.getElementById('confirmBackdrop').style.display = 'flex';
        }

        function confirmCancel(){
            document.getElementById('confirmBackdrop').style.display = 'none';
        }
        function confirmSubmit(){
            document.getElementById('confirmBackdrop').style.display = 'none';
            document.getElementById('violationForm').submit();
        }
    </script>

    <!-- Confirmation Modal -->
    <div id="confirmBackdrop" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" style="position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 1000;">
        <div class="modal" style="background: #ffffff; max-width: 520px; width: 92%; border-radius: 8px; padding: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.2);">
            <h2 id="confirmTitle" style="margin-top: 0;">Confirm submission</h2>
            <p><strong>Please verify the student and selected violations:</strong></p>
            <ul id="confirmList"></ul>
            <div class="actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                <button class="confirmCancelBtn" type="button" onclick="confirmCancel()">Cancel</button>
                <button class="confirmSubmitBtn" type="button" onclick="confirmSubmit()">Submit</button>
            </div>
        </div>
    </div>
</body>
</html>