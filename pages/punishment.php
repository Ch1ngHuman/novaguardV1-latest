<?php
session_start();
require_once '../connect.php';

// Check user role and set appropriate dashboard link
$dashboardLink = '../index.php'; // default redirect
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $dashboardLink = 'admin-dashboard.php';
    } elseif ($_SESSION['role'] === 'guard') {
        $dashboardLink = 'guard-dashboard.php';
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get current school year
$school_year_query = "SELECT id, school_year FROM school_year WHERE is_current = 1 LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year_id = 1; // Default fallback
$current_school_year_name = "2025-2026"; // Default fallback

if ($school_year_result && $school_year_result->num_rows > 0) {
    $school_year_row = $school_year_result->fetch_assoc();
    $current_school_year_id = $school_year_row['id'];
    $current_school_year_name = $school_year_row['school_year'];
}

// Get current semester name and ID
$current_semester = getCurrentSemesterName($conn);
$current_semester_id = getCurrentSemester($conn);

// Fetch JHS students with violations for current school year (JHS doesn't use semester)
$jhs_query = "SELECT
    j.id as student_id,
    CONCAT(j.firstName, ' ', COALESCE(j.middleName, ''), ' ', j.lastName) as full_name,
    j.year_lvl,
    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
    COUNT(DISTINCT v.violation_type, v.date_recorded) as total_violations,
    GROUP_CONCAT(DISTINCT DATE_FORMAT(v.date_recorded, '%Y-%m-%d') SEPARATOR ', ') as date_reported,
    s.sanction_hours,
    s.status as sanction_status
    FROM jhs_students j
    JOIN hs_violations v ON j.id = v.student_id
    LEFT JOIN student_sanctions s ON j.id = s.student_id AND s.student_type = 'jhs' AND s.school_year_id = ? AND s.status = 'active'
    WHERE v.student_type = 'jhs' AND v.school_year_id = ?
    GROUP BY j.id, j.firstName, j.middleName, j.lastName, j.year_lvl
    ORDER BY j.firstName ASC, j.middleName ASC, j.lastName ASC";

$jhs_stmt = $conn->prepare($jhs_query);
$jhs_stmt->bind_param('ii', $current_school_year_id, $current_school_year_id);
$jhs_stmt->execute();
$jhs_result = $jhs_stmt->get_result();
$jhs_violations = $jhs_result ? $jhs_result->fetch_all(MYSQLI_ASSOC) : [];
$jhs_stmt->close();

// Fetch SHS students with violations for current school year and semester
$shs_query = "SELECT
    s.id as student_id,
    CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.lastName) as full_name,
    s.strand,
    s.year_lvl,
    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
    COUNT(DISTINCT v.violation_type, v.date_recorded) as total_violations,
    GROUP_CONCAT(DISTINCT DATE_FORMAT(v.date_recorded, '%Y-%m-%d') SEPARATOR ', ') as date_reported,
    st.sanction_hours,
    st.status as sanction_status
    FROM shs_students s
    JOIN shs_violations v ON s.id = v.student_id
    LEFT JOIN student_sanctions st ON s.id = st.student_id AND st.student_type = 'shs' AND st.school_year_id = ? AND st.status = 'active'
    WHERE v.school_year_id = ? AND v.semester_id = ?
    GROUP BY s.id, s.firstName, s.middleName, s.lastName, s.strand, s.year_lvl
    ORDER BY s.firstName ASC, s.middleName ASC, s.lastName ASC";

$shs_stmt = $conn->prepare($shs_query);
$shs_stmt->bind_param('iii', $current_school_year_id, $current_school_year_id, $current_semester_id);
$shs_stmt->execute();
$shs_result = $shs_stmt->get_result();
$shs_violations = $shs_result ? $shs_result->fetch_all(MYSQLI_ASSOC) : [];
$shs_stmt->close();

// Fetch College students with violations for current school year and semester
$college_query = "SELECT
    c.id as student_id,
    CONCAT(c.firstName, ' ', COALESCE(c.middleName, ''), ' ', c.LastName) as full_name,
    c.course,
    c.year_lvl,
    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
    COUNT(DISTINCT v.violation_type, v.date_recorded) as total_violations,
    GROUP_CONCAT(DISTINCT DATE_FORMAT(v.date_recorded, '%Y-%m-%d') SEPARATOR ', ') as date_reported,
    sc.sanction_hours,
    sc.status as sanction_status
    FROM college_students c
    JOIN (
        SELECT student_id, violation_type, date_recorded, school_year_id, semester_id FROM college_violations
        UNION ALL
        SELECT student_id, violation_type, date_recorded, school_year_id, semester_id FROM college_crim_violations
    ) v ON c.id = v.student_id
    LEFT JOIN student_sanctions sc ON c.id = sc.student_id AND sc.student_type = 'college' AND sc.school_year_id = ? AND sc.status = 'active'
    WHERE v.school_year_id = ? AND v.semester_id = ?
    GROUP BY c.id, c.firstName, c.middleName, c.lastName, c.year_lvl
    ORDER BY c.firstName ASC, c.middleName ASC, c.lastName ASC";

$college_stmt = $conn->prepare($college_query);
$college_stmt->bind_param('iii', $current_school_year_id, $current_school_year_id, $current_semester_id);
$college_stmt->execute();
$college_result = $college_stmt->get_result();
$college_violations = $college_result ? $college_result->fetch_all(MYSQLI_ASSOC) : [];
$college_stmt->close();

// Function to get row background color based on sanction hours
function getSanctionRowColor($sanction_hours, $sanction_status) {
    // If no active sanction, return white
    if (empty($sanction_hours) || $sanction_status !== 'active') {
        return 'white';
    }

    // Extract numeric value from sanction hours (e.g., "3 Hours" -> 3)
    $hours = (int)filter_var($sanction_hours, FILTER_SANITIZE_NUMBER_INT);

    // Return color based on sanction hours
    if ($hours <= 3) {
        return '#cbfeff'; // Light blue for <= 3 hours
    } elseif ($hours <= 6) {
        return '#fff2a6'; // Light yellow for <= 6 hours
    } else { // >= 8 hours
        return '#ffcccb'; // Light red for >= 8 hours
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Punishment - NovaGuard</title>
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            90% { opacity: 0; }
            100% {
                opacity: 0;
                visibility: hidden;
            }
        }
        .success-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: fit-content;
            background-color: #4CAF50;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeOut 4s 1s forwards;
            font-size: 1.1em;
            z-index: 1000;
            display: none;
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
            max-width: 400px;
            width: 90%;
        }

        .confirmation-message {
            font-size: 1.2em;
            margin-bottom: 25px;
            color: #333;
        }

        .confirmation-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm-yes {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-confirm-yes:hover {
            background: #d32f2f;
        }

        .btn-confirm-no {
            background: #757575;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-confirm-no:hover {
            background: #616161;
        }


        .index-page {
            position: relative;
            padding-top: 20px;
        }

        /* Sanction dropdown styles */
        .sanction-dropdown {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 150px;
            padding: 10px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .sanction-select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            background: white;
        }

        .sanction-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .btn-save {
            background: #4CAF50;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-save:hover:not(:disabled) {
            background: #45a049;
        }

        .btn-save:disabled {
            background: #cccccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-remove {
            background: #f44336;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-remove:hover:not(:disabled) {
            background: #d32f2f;
        }

        .btn-remove:disabled {
            background: #cccccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-cancel {
            background: #757575;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background: #616161;
        }
    </style>
</head>
<body>
    <!-- Success Notification -->
    <div class="success-notification" id="successNotification">
        Sanction added successfully!
    </div>

    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-message">
                Are you sure you want to remove sanctions?
            </div>
            <div class="confirmation-buttons">
                <button class="btn-confirm-yes" id="confirmYes">Yes</button>
                <button class="btn-confirm-no" id="confirmNo">No</button>
            </div>
        </div>
    </div>

    <div class="index-page">
        <div class="icon-back-wrapper top20">
            <a href="<?php echo htmlspecialchars($dashboardLink); ?>" class="icon-back-btn purple" aria-label="Back to Dashboard">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>

        <div class="header-section" style="background: linear-gradient(135deg, #667eea 0%, #282bf0 100%); padding: 20px; border-radius: 10px; margin: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">    
            <h1 style="padding-top: 40px; color: #231f1f;">Assign Punishment</h1>
            <h3 style="text-align: center; color:rgb(255, 255, 255);">S.Y. <?php echo htmlspecialchars($current_school_year_name); ?> - <?php echo htmlspecialchars($current_semester); ?></h3>
        </div>

        <!-- Search and Filter Controls -->
        <div style="margin: 20px 0; display: flex; justify-content: center; gap: 2em; align-items: center;">
            <input type="text" id="searchInput" placeholder="Search by name..." style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 220px;" autocomplete="off">
            <select id="departmentFilter" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                <option value="all">All Departments</option>
                <option value="jhs">JHS Students</option>
                <option value="shs">SHS Students</option>
                <option value="college">College Students</option>
            </select>
        </div>

        <!-- JHS Students Table -->
        <div style="margin: 20px;" id="jhs-section">
            <div class="wrapper">
                <p><strong>JHS Students</strong></p>
            </div>
            <div>
                <table id="jhsTable" style="width:100%; border-collapse:collapse; margin: 0 auto;">
                    <thead>
                        <tr style="background:#667eea; color:white;">
                            <th style="padding:8px;">Name</th>
                            <th style="padding:8px;">Year Grade</th>
                            <th style="padding:8px;">Violation/s</th>
                            <th style="padding:8px;">Date Reported</th>
                            <th style="padding:8px;">Sanctions</th>
                            <th style="padding:8px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jhs_violations)): ?>
                            <tr style="background:white; border-bottom:1px solid #d0e7d0;">
                                <td colspan="6" style="text-align: center; padding:8px;">No violations recorded for JHS students</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jhs_violations as $violation): ?>
                                <tr style="background:<?php echo getSanctionRowColor($violation['sanction_hours'], $violation['sanction_status']); ?>; border-bottom:1px solid #d0e7d0;">
                                    <td style="padding:8px;" class="name-cell"><?php echo htmlspecialchars($violation['full_name']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['year_lvl']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['violations']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['date_reported']); ?></td>
                                    <td style="padding:8px;" class="sanction-cell">
                                        <?php
                                        if (!empty($violation['sanction_hours']) && $violation['sanction_status'] === 'active') {
                                            $sanction_text = htmlspecialchars($violation['sanction_hours']);
                                            // Check if "Hours" is already included to avoid duplication
                                            if (strpos(strtolower($sanction_text), 'hours') === false) {
                                                echo $sanction_text . ' Hours';
                                            } else {
                                                echo $sanction_text;
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding:8px;">
                                        <button onclick="addSanction(this, <?php echo $violation['student_id']; ?>, 'jhs')" style="background: #667eea; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; margin-right: 5px;">Add Sanction</button>
                                        <button onclick="removeSanction(this, <?php echo $violation['student_id']; ?>, 'jhs')" style="background: #ff4757; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer;">Remove Sanction</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SHS Students Table -->
        <div style="margin: 20px;" id="shs-section">
            <div class="wrapper">
                <p><strong>SHS Students</strong></p>
            </div>
            <div>
                <table id="shsTable" style="width:100%; border-collapse:collapse; margin: 0 auto;">
                    <thead>
                        <tr style="background:#667eea; color:white;">
                            <th style="padding:8px;">Name</th>
                            <th style="padding:8px;">Strand</th>
                            <th style="padding:8px;">Year Level</th>
                            <th style="padding:8px;">Violation/s</th>
                            <th style="padding:8px;">Date Reported</th>
                            <th style="padding:8px;">Sanctions</th>
                            <th style="padding:8px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shs_violations)): ?>
                            <tr style="background:white; border-bottom:1px solid #d0e7d0;">
                                <td colspan="7" style="text-align: center; padding:8px;">No violations recorded for SHS students</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shs_violations as $violation): ?>
                                <tr style="background:<?php echo getSanctionRowColor($violation['sanction_hours'], $violation['sanction_status']); ?>; border-bottom:1px solid #d0e7d0;">
                                    <td style="padding:8px;" class="name-cell"><?php echo htmlspecialchars($violation['full_name']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['strand']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['year_lvl']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['violations']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['date_reported']); ?></td>
                                    <td style="padding:8px;" class="sanction-cell">
                                        <?php
                                        if (!empty($violation['sanction_hours']) && $violation['sanction_status'] === 'active') {
                                            $sanction_text = htmlspecialchars($violation['sanction_hours']);
                                            // Check if "Hours" is already included to avoid duplication
                                            if (strpos(strtolower($sanction_text), 'hours') === false) {
                                                echo $sanction_text . ' Hours';
                                            } else {
                                                echo $sanction_text;
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding:8px;">
                                        <button onclick="addSanction(this, <?php echo $violation['student_id']; ?>, 'shs')" style="background: #667eea; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; margin-right: 5px;">Add Sanction</button>
                                        <button onclick="removeSanction(this, <?php echo $violation['student_id']; ?>, 'shs')" style="background: #ff4757; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer;">Remove Sanction</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- College Students Table -->
        <div style="margin: 20px;" id="college-section">
            <div class="wrapper">
                <p><strong>COLLEGE Students</strong></p>
            </div>
            <div>
                <table id="collegeTable" style="width:100%; border-collapse:collapse; margin: 0 auto;">
                    <thead>
                        <tr style="background:#667eea; color:white;">
                            <th style="padding:8px;">Name</th>
                            <th style="padding:8px;">Course</th>
                            <th style="padding:8px;">Year Level</th>
                            <th style="padding:8px;">Violation/s</th>
                            <th style="padding:8px;">Date Reported</th>
                            <th style="padding:8px;">Sanctions</th>
                            <th style="padding:8px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($college_violations)): ?>
                            <tr style="background:white; border-bottom:1px solid #d0e7d0;">
                                <td colspan="7" style="text-align: center; padding:8px;">No violations recorded for College students</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($college_violations as $violation): ?>
                                <tr style="background:<?php echo getSanctionRowColor($violation['sanction_hours'], $violation['sanction_status']); ?>; border-bottom:1px solid #d0e7d0;">
                                    <td style="padding:8px;" class="name-cell"><?php echo htmlspecialchars($violation['full_name']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['course']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['year_lvl']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['violations']); ?></td>
                                    <td style="padding:8px;"><?php echo htmlspecialchars($violation['date_reported']); ?></td>
                                    <td style="padding:8px;" class="sanction-cell">
                                        <?php
                                        if (!empty($violation['sanction_hours']) && $violation['sanction_status'] === 'active') {
                                            $sanction_text = htmlspecialchars($violation['sanction_hours']);
                                            // Check if "Hours" is already included to avoid duplication
                                            if (strpos(strtolower($sanction_text), 'hours') === false) {
                                                echo $sanction_text . ' Hours';
                                            } else {
                                                echo $sanction_text;
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding:8px;">
                                        <button onclick="addSanction(this, <?php echo $violation['student_id']; ?>, 'college')" style="background: #667eea; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; margin-right: 5px;">Add Sanction</button>
                                        <button onclick="removeSanction(this, <?php echo $violation['student_id']; ?>, 'college')" style="background: #ff4757; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer;">Remove Sanction</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            // Search and department filter for all tables
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const departmentFilter = document.getElementById('departmentFilter');
                const tables = [
                    document.getElementById('jhsTable'),
                    document.getElementById('shsTable'),
                    document.getElementById('collegeTable')
                ];

                function filterTables() {
                    const search = searchInput.value.trim().toLowerCase();
                    const dept = departmentFilter.value;
                    tables.forEach((table, idx) => {
                        // Show/hide tables based on department filter
                        if (dept === 'all' || (dept === 'jhs' && idx === 0) || (dept === 'shs' && idx === 1) || (dept === 'college' && idx === 2)) {
                            table.parentElement.parentElement.style.display = '';
                        } else {
                            table.parentElement.parentElement.style.display = 'none';
                        }
                        const tbody = table.querySelector('tbody');
                        const rows = Array.from(tbody.querySelectorAll('tr'));
                        // Filter
                        rows.forEach(row => {
                            const nameCell = row.querySelector('.name-cell');
                            if (!nameCell) return;
                            const name = nameCell.textContent.toLowerCase();
                            if (search === '' || name.includes(search)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
                searchInput.addEventListener('input', filterTables);
                departmentFilter.addEventListener('change', filterTables);
                filterTables(); // Initial call to set default view
            });

            // Add Sanction functionality
            function addSanction(button, studentId, studentType) {
                const row = button.closest('tr');
                const sanctionCell = row.querySelector('.sanction-cell');

                // Check if dropdown already exists
                if (sanctionCell.querySelector('.sanction-dropdown')) {
                    return;
                }

                // Store original content
                const originalContent = sanctionCell.innerHTML;

                // Create dropdown container
                const dropdownContainer = document.createElement('div');
                dropdownContainer.className = 'sanction-dropdown';

                // Create select dropdown
                const select = document.createElement('select');
                select.className = 'sanction-select';

                // Add options
                const options = [
                    { value: '', text: 'Select Sanction' },
                    { value: '3 hours', text: '3 Hours' },
                    { value: '6 hours', text: '6 Hours' },
                    { value: '8 hours', text: '8 Hours' }
                ];

                options.forEach(option => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option.value;
                    optionElement.textContent = option.text;
                    select.appendChild(optionElement);
                });

                // Create button container
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'sanction-buttons';

                // Create Save button
                const saveButton = document.createElement('button');
                saveButton.className = 'btn-save';
                saveButton.textContent = 'Save';
                saveButton.disabled = true; // Initially disabled
                saveButton.onclick = function() {
                    saveSanction(studentId, studentType, select.value, sanctionCell, originalContent);
                };

                // Create Cancel button
                const cancelButton = document.createElement('button');
                cancelButton.className = 'btn-cancel';
                cancelButton.textContent = 'Cancel';
                cancelButton.onclick = function() {
                    sanctionCell.innerHTML = originalContent;
                };

                // Add event listener to enable/disable Save button based on selection
                select.addEventListener('change', function() {
                    saveButton.disabled = !select.value || select.value === '';
                });

                // Assemble dropdown
                buttonContainer.appendChild(saveButton);
                buttonContainer.appendChild(cancelButton);
                dropdownContainer.appendChild(select);
                dropdownContainer.appendChild(buttonContainer);

                // Replace cell content
                sanctionCell.innerHTML = '';
                sanctionCell.appendChild(dropdownContainer);
            }

            function saveSanction(studentId, studentType, sanctionValue, sanctionCell, originalContent) {
                if (!sanctionValue) {
                    alert('Please select a sanction option.');
                    return;
                }

                // Show loading state
                sanctionCell.innerHTML = '<div style="text-align: center; color: #666;">Saving...</div>';

                // Prepare data for AJAX request
                const data = {
                    student_id: studentId,
                    student_type: studentType,
                    sanction_hours: sanctionValue
                };

                // Send AJAX request to save sanction
                fetch('save_sanction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Update display with total sanction hours from server
                        sanctionCell.innerHTML = result.total_hours || sanctionValue;

                        // Update row color based on new sanction
                        updateRowColor(sanctionCell);

                        // Show success notification
                        showSuccessNotification('Sanction added successfully!');
                    } else {
                        // Show error and restore original content
                        alert('Error: ' + result.message);
                        sanctionCell.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Error saving sanction:', error);
                    alert('An error occurred while saving the sanction. Please try again.');
                    sanctionCell.innerHTML = originalContent;
                });
            }

            // Remove Sanction functionality
            function removeSanction(button, studentId, studentType) {
                const row = button.closest('tr');
                const sanctionCell = row.querySelector('.sanction-cell');

                // Check if student has active sanction
                if (sanctionCell.innerHTML.trim() === '-') {
                    alert('No active sanction to remove.');
                    return;
                }

                // Store original content for cancel functionality
                const originalContent = sanctionCell.innerHTML;

                // Create dropdown container
                const dropdownContainer = document.createElement('div');
                dropdownContainer.className = 'sanction-dropdown';

                // Create select element
                const select = document.createElement('select');
                select.className = 'sanction-select';

                // Add options
                const options = ['', '3 hours', '6 hours', '8 hours'];
                options.forEach(option => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option;
                    optionElement.textContent = option || 'Select hours to remove';
                    select.appendChild(optionElement);
                });

                // Create button container
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'sanction-buttons';

                // Create Remove button
                const removeButton = document.createElement('button');
                removeButton.className = 'btn btn-remove';
                removeButton.textContent = 'Remove';
                removeButton.disabled = true; // Initially disabled
                removeButton.onclick = function() {
                    showConfirmationModal(studentId, studentType, select.value, sanctionCell);
                };

                // Create Cancel button
                const cancelButton = document.createElement('button');
                cancelButton.className = 'btn btn-cancel';
                cancelButton.textContent = 'Cancel';
                cancelButton.onclick = function() {
                    // Restore original content
                    sanctionCell.innerHTML = originalContent;
                };

                // Add event listener to enable/disable Remove button based on selection
                select.addEventListener('change', function() {
                    removeButton.disabled = !select.value || select.value === '';
                });

                // Assemble dropdown
                buttonContainer.appendChild(removeButton);
                buttonContainer.appendChild(cancelButton);
                dropdownContainer.appendChild(select);
                dropdownContainer.appendChild(buttonContainer);

                // Replace cell content
                sanctionCell.innerHTML = '';
                sanctionCell.appendChild(dropdownContainer);
            }

            function reduceSanction(studentId, studentType, sanctionValue, sanctionCell) {
                if (!sanctionValue) {
                    alert('Please select hours to remove.');
                    return;
                }

                // Show loading state
                const originalContent = sanctionCell.innerHTML;
                sanctionCell.innerHTML = '<div style="text-align: center; color: #666;">Removing...</div>';

                // Prepare data for AJAX request
                const data = {
                    student_id: studentId,
                    student_type: studentType,
                    sanction_hours: sanctionValue
                };

                // Send AJAX request to reduce sanction
                fetch('reduce_sanction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Check if sanction was completely removed (violations deleted)
                        if (result.remaining_hours === '-' && result.message.includes('All violations and sanctions have been cleared')) {
                            // Show success notification and refresh page to remove violations from display
                            showSuccessNotification('Sanction completed! All violations cleared.');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // Update display with remaining sanction hours from server
                            sanctionCell.innerHTML = result.remaining_hours || '-';

                            // Update row color based on remaining sanction
                            updateRowColor(sanctionCell);

                            // Show success notification for removal
                            showSuccessNotification('Sanction removed successfully!');
                        }
                    } else {
                        // Show error and restore original content
                        alert('Error: ' + result.message);
                        sanctionCell.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Error reducing sanction:', error);
                    alert('An error occurred while reducing the sanction. Please try again.');
                    sanctionCell.innerHTML = originalContent;
                });
            }

            // Function to show success notification
            function showSuccessNotification(message) {
                const notification = document.getElementById('successNotification');
                if (notification) {
                    // Update the message
                    notification.textContent = message || 'Action completed successfully!';
                    notification.style.display = 'block';

                    // Auto-hide after 5 seconds
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 5000);
                }
            }

            // Function to get row background color based on sanction hours (JavaScript version)
            function getSanctionRowColor(sanctionText) {
                // If no sanction or just "-", return white
                if (!sanctionText || sanctionText.trim() === '-') {
                    return 'white';
                }

                // Extract numeric value from sanction text (e.g., "3 Hours" -> 3)
                const hours = parseInt(sanctionText.match(/\d+/));

                // Return color based on sanction hours
                if (isNaN(hours)) {
                    return 'white';
                } else if (hours <= 3) {
                    return '#cbfeff'; // Light blue for <= 3 hours
                } else if (hours <= 6) {
                    return '#fff2a6'; // Light yellow for <= 6 hours
                } else { // >= 8 hours
                    return '#ffcccb'; // Light red for >= 8 hours
                }
            }

            // Function to update row color based on sanction
            function updateRowColor(sanctionCell) {
                const row = sanctionCell.closest('tr');
                const sanctionText = sanctionCell.textContent || sanctionCell.innerText;
                const newColor = getSanctionRowColor(sanctionText);
                row.style.backgroundColor = newColor;
            }

            // Function to show confirmation modal
            function showConfirmationModal(studentId, studentType, sanctionValue, sanctionCell) {
                const modal = document.getElementById('confirmationModal');
                const confirmYes = document.getElementById('confirmYes');
                const confirmNo = document.getElementById('confirmNo');

                // Show modal
                modal.style.display = 'flex';

                // Handle Yes button click
                confirmYes.onclick = function() {
                    modal.style.display = 'none';
                    reduceSanction(studentId, studentType, sanctionValue, sanctionCell);
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
            }
        </script>
    </div>

</body>
</html>