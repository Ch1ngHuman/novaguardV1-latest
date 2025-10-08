<?php
session_start();
require_once '../connect.php';

// Handle update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['edit_department'])) {
    $id = intval($_POST['edit_id']);
    $department = $_POST['edit_department'];
    $name = $_POST['edit_name'];
    $email = $_POST['edit_email'];
    $year_lvl = $_POST['edit_year_lvl'];
    $strand = $_POST['edit_strand'] ?? '';
    $course = $_POST['edit_course'] ?? '';
    // Split name
    $nameParts = explode(' ', $name, 3);
    $firstName = $nameParts[0] ?? '';
    $middleName = $nameParts[1] ?? '';
    $lastName = $nameParts[2] ?? '';
    // Get current school year
    $currentSchoolYearQuery = "SELECT school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
    $currentSchoolYearResult = $conn->query($currentSchoolYearQuery);
    $currentSchoolYear = $currentSchoolYearResult->fetch_assoc()['school_year'];

    if ($department === 'JHS') {
        $stmt = $conn->prepare("UPDATE jhs_students SET firstName=?, middleName=?, lastName=?, email=?, year_lvl=?, school_year=? WHERE id=?");
        $stmt->bind_param("ssssssi", $firstName, $middleName, $lastName, $email, $year_lvl, $currentSchoolYear, $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($department === 'SHS') {
        $stmt = $conn->prepare("UPDATE shs_students SET firstName=?, middleName=?, lastName=?, email=?, year_lvl=?, strand=?, school_year=? WHERE id=?");
        $stmt->bind_param("sssssssi", $firstName, $middleName, $lastName, $email, $year_lvl, $strand, $currentSchoolYear, $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($department === 'College') {
        $stmt = $conn->prepare("UPDATE college_students SET firstName=?, middleName=?, lastName=?, email=?, year_lvl=?, course=?, school_year=? WHERE id=?");
        $stmt->bind_param("sssssssi", $firstName, $middleName, $lastName, $email, $year_lvl, $course, $currentSchoolYear, $id);
        $stmt->execute();
        $stmt->close();
    }
    // Refresh to show updated data
    header("Location: registered_students.php");

    // Set success message in session
    $_SESSION['success_message'] = "Student information updated successfully!";
    
    // Redirect back to the page
    header("Location: registered_students.php");
    exit();
}

// Get current school year
$currentSchoolYearQuery = "SELECT school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$currentSchoolYearResult = $conn->query($currentSchoolYearQuery);
$currentSchoolYear = $currentSchoolYearResult->fetch_assoc()['school_year'] ?? '';

// Fetch JHS students for current school year
$jhs = $conn->query("SELECT id, CONCAT(firstName, ' ', middleName, ' ', lastName) AS name, email, 'JHS' AS department, year_lvl, '' AS strand, '' AS course 
                    FROM jhs_students 
                    WHERE school_year = '$currentSchoolYear'");
// Fetch SHS students for current school year
$shs = $conn->query("SELECT id, CONCAT(firstName, ' ', middleName, ' ', lastName) AS name, email, 'SHS' AS department, year_lvl, strand, '' AS course 
                    FROM shs_students 
                    WHERE school_year = '$currentSchoolYear'");
// Fetch College students for current school year
$college = $conn->query("SELECT id, CONCAT(firstName, ' ', middleName, ' ', lastName) AS name, email, 'College' AS department, year_lvl, '' AS strand, course 
                        FROM college_students 
                        WHERE school_year = '$currentSchoolYear'");

$students = [];
if ($jhs) {
    while ($row = $jhs->fetch_assoc()) {
        $students[] = $row;
    }
}
if ($shs) {
    while ($row = $shs->fetch_assoc()) {
        $students[] = $row;
    }
}
if ($college) {
    while ($row = $college->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered-students</title>
    <link rel="stylesheet" href="../styles/style.css">
    <script src="../js/scripts/script.js"></script>
    <style>
        /* Success Notification Styles */
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

        .error-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: fit-content;
            background-color: #f44336;
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
    </style>
</head>
<body>
    <!-- Success Notification -->
    <div class="success-notification" id="successNotification">
        Student deleted successfully!
    </div>

    <!-- Error Notification -->
    <div class="error-notification" id="errorNotification">
        Cannot delete student with active sanctions!
    </div>

    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-message">
                Are you sure you want to delete this student?
            </div>
            <div class="confirmation-buttons">
                <button class="btn-confirm-yes" id="confirmYes">Yes</button>
                <button class="btn-confirm-no" id="confirmNo">No</button>
            </div>
        </div>
    </div>

    <div class="index-page">
        <?php
        // Display success message if it exists
        if (isset($_SESSION['success_message'])) {
            echo '<div style="background-color: #4CAF50; color: white; padding: 15px; margin: 10px; border-radius: 5px; text-align: center;">';
            echo $_SESSION['success_message'];
            echo '</div>';
            unset($_SESSION['success_message']); // Clear the message
        }
        ?>
        <div class="icon-back-wrapper">
            <a href="admin-dashboard.php" class="icon-back-btn" aria-label="Back to Dashboard">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
        <div class="header-section">      
            <h1>Registered Students</h1>
            <h3>School Year: <?php echo htmlspecialchars($currentSchoolYear); ?></h3>
        </div>
        <!-- Search and Sort Controls -->
        <div style="margin: 20px 0; display: flex; justify-content: center; gap: 2em; align-items: center;">
            <form method="get" action="registered_students.php" id="searchSortForm" style="display: flex; gap: 1em; align-items: center;">
                <input 
                    type="text" 
                    name="search" 
                    id="searchInput"
                    placeholder="Search by name or email..." 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                    style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 220px;"
                    autocomplete="off"
                >
                <select name="department_filter" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; margin-right: 10px;" onchange="document.getElementById('searchSortForm').submit();">
                    <option value="">All Departments</option>
                    <option value="JHS" <?php if(isset($_GET['department_filter']) && $_GET['department_filter']=='JHS') echo 'selected'; ?>>JHS Students</option>
                    <option value="SHS" <?php if(isset($_GET['department_filter']) && $_GET['department_filter']=='SHS') echo 'selected'; ?>>SHS Students</option>
                    <option value="College" <?php if(isset($_GET['department_filter']) && $_GET['department_filter']=='College') echo 'selected'; ?>>College Students</option>
                </select>
                <!-- No search or reset button -->
            </form>
        </div>
        <script>
        // Client-side search filter for students table
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const table = document.querySelector('table');
            if (!searchInput || !table) return;

            searchInput.addEventListener('input', function() {
                const search = searchInput.value.trim().toLowerCase();
                // Find tbody rows
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    // Get name and email columns (assume order: Name, Email, ...)
                    const nameCell = row.children[0]?.textContent.toLowerCase() || '';
                    const emailCell = row.children[1]?.textContent.toLowerCase() || '';
                    if (nameCell.includes(search) || emailCell.includes(search)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
        </script>
        <?php
        // --- PHP: Filter and Sort Students Array ---
        // Filter by department
        if (isset($_GET['department_filter']) && $_GET['department_filter'] !== '') {
            $department = $_GET['department_filter'];
            $students = array_filter($students, function($student) use ($department) {
                return $student['department'] === $department;
            });
        }

        // Filter by search
        if (isset($_GET['search']) && trim($_GET['search']) !== '') {
            $search = strtolower(trim($_GET['search']));
            $students = array_filter($students, function($student) use ($search) {
                return (strpos(strtolower($student['name']), $search) !== false) ||
                       (strpos(strtolower($student['email']), $search) !== false);
            });
        }
        // Sort
        // Default sort by name (A-Z) if no sort option is selected
        $sort_by = isset($_GET['sort_by']) && $_GET['sort_by'] !== '' ? $_GET['sort_by'] : 'name_asc';
        
        usort($students, function($a, $b) use ($sort_by) {
            switch ($sort_by) {
                case 'name_asc':
                    return strcasecmp($a['name'], $b['name']);
                case 'name_desc':
                    return strcasecmp($b['name'], $a['name']);
                case 'department_asc':
                    return strcasecmp($a['department'], $b['department']);
                case 'department_desc':
                    return strcasecmp($b['department'], $a['department']);
                case 'year_lvl_asc':
                    return strcasecmp($a['year_lvl'], $b['year_lvl']);
                case 'year_lvl_desc':
                    return strcasecmp($b['year_lvl'], $a['year_lvl']);
                default:
                    return strcasecmp($a['name'], $b['name']); // Default to name ascending
            }
        });
                // Re-index array for correct edit index
                $students = array_values($students);
        ?>
                <?php if (empty($students)): ?>
            <div style="text-align: center; padding: 20px; background: white; border-radius: 5px; margin: 20px 0;">
                <p style="color: #666; font-size: 1.1em;">No students found for school year <?php echo htmlspecialchars($currentSchoolYear); ?></p>
            </div>
        <?php endif; ?>
        
        <div>
                    <table style="width:100%; border-collapse:collapse; margin: 0 auto;">
                        <thead>
                    <tr style="background:#667eea; color:white;">
                        <th style="padding:8px;">Name</th>
                        <th style="padding:8px;">Email</th>
                        <th style="padding:8px;">Department</th>
                        <th style="padding:8px;">Year Level</th>
                        <th style="padding:8px;">Strand/Course</th>
                        <th style="padding:8px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $student): ?>
                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $i): ?>
                        <tr style="background:#f3f0ff; border-bottom:1px solid #eee;">
                            <form method="post" action="">
                                <td style="padding:8px;">
                                    <input type="text" name="edit_name" value="<?php echo htmlspecialchars($student['name']); ?>" required style="width:100%;">
                                </td>
                                <td style="padding:8px;">
                                    <input type="email" name="edit_email" value="<?php echo htmlspecialchars($student['email']); ?>" required style="width:100%;">
                                </td>
                                <td style="padding:8px;">
                                    <select name="edit_department" id="edit_department_<?php echo $i; ?>" required style="width:100%;" disabled>
                                        <option value="JHS" <?php if($student['department']==='JHS')echo'selected';?>>JHS</option>
                                        <option value="SHS" <?php if($student['department']==='SHS')echo'selected';?>>SHS</option>
                                        <option value="College" <?php if($student['department']==='College')echo'selected';?>>College</option>
                                    </select>
                                    <input type="hidden" name="edit_department" value="<?php echo htmlspecialchars($student['department']); ?>">
                                </td>
                                <td style="padding:8px;">
                                    <!-- Year Level select -->
                                    <select name="edit_year_lvl" id="edit_year_lvl_<?php echo $i; ?>" required style="width:100%;">
                                        <?php if ($student['department'] === 'JHS'): ?>
                                            <option value="Grade7" <?php if($student['year_lvl']==='Grade7')echo'selected';?>>Grade 7</option>
                                            <option value="Grade8" <?php if($student['year_lvl']==='Grade8')echo'selected';?>>Grade 8</option>
                                            <option value="Grade9" <?php if($student['year_lvl']==='Grade9')echo'selected';?>>Grade 9</option>
                                            <option value="Grade10" <?php if($student['year_lvl']==='Grade10')echo'selected';?>>Grade 10</option>
                                        <?php elseif ($student['department'] === 'SHS'): ?>
                                            <option value="Grade11" <?php if($student['year_lvl']==='Grade11')echo'selected';?>>Grade 11</option>
                                            <option value="Grade12" <?php if($student['year_lvl']==='Grade12')echo'selected';?>>Grade 12</option>
                                        <?php elseif ($student['department'] === 'College'): ?>
                                            <option value="1stYear" <?php if($student['year_lvl']==='1stYear')echo'selected';?>>1st Year</option>
                                            <option value="2ndYear" <?php if($student['year_lvl']==='2ndYear')echo'selected';?>>2nd Year</option>
                                            <option value="3rdYear" <?php if($student['year_lvl']==='3rdYear')echo'selected';?>>3rd Year</option>
                                            <option value="4thYear" <?php if($student['year_lvl']==='4thYear')echo'selected';?>>4th Year</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td style="padding:8px;">
                                    <!-- Strand/Course select -->
                                    <?php if ($student['department'] === 'SHS'): ?>
                                        <select name="edit_strand" id="edit_strand_<?php echo $i; ?>" style="width:100%;">
                                            <option value="STEM" <?php if($student['strand']==='STEM')echo'selected';?>>STEM</option>
                                            <option value="HUMSS" <?php if($student['strand']==='HUMSS')echo'selected';?>>HUMSS</option>
                                            <option value="ABM" <?php if($student['strand']==='ABM')echo'selected';?>>ABM</option>
                                            <option value="TVL" <?php if($student['strand']==='TVL')echo'selected';?>>TVL</option>
                                        </select>
                                    <?php elseif ($student['department'] === 'College'): ?>
                                        <select name="edit_course" id="edit_course_<?php echo $i; ?>" style="width:100%;">
                                            <option value="BTVTEd" <?php if($student['course']==='BTVTEd')echo'selected';?>>BTVTEd</option>
                                            <option value="BSCRIM" <?php if($student['course']==='BSCRIM')echo'selected';?>>BSCRIM</option>
                                            <option value="BSIS" <?php if($student['course']==='BSIS')echo'selected';?>>BSIS</option>
                                            <option value="BSEntrep" <?php if($student['course']==='BSEntrep')echo'selected';?>>BSEntrep</option>
                                            <option value="BSAIS" <?php if($student['course']==='BSAIS')echo'selected';?>>BSAIS</option>
                                        </select>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <input type="hidden" name="edit_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" style="background:#667eea; color:white; border:none; border-radius:5px; padding:6px 12px;">Save</button>
                                    <a href="registered_students.php" style="background:#aaa; color:white; border-radius:5px; padding:6px 12px; text-decoration:none; margin-left:4px;">Cancel</a>
                                </td>
                            </form>
                        </tr>
                        <?php else: ?>
                        <tr style="background:white; border-bottom:1px solid #eee;">
                            <td style="padding:8px;"><?php echo htmlspecialchars($student['name']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($student['email']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($student['department']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($student['year_lvl']); ?></td>
                            <td style="padding:8px;">
                                <?php echo $student['department'] === 'SHS' ? htmlspecialchars($student['strand']) : ($student['department'] === 'College' ? htmlspecialchars($student['course']) : '-'); ?>
                            </td>
                            <td style="padding:8px;">
                                <a href="registered_students.php?edit=<?php echo $i; ?>" style="background:#667eea; color:white; border-radius:5px; padding:6px 12px; text-decoration:none;">Edit</a>
                                <button onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo $student['department']; ?>', '<?php echo htmlspecialchars($student['name']); ?>')" style="background:#ff4757; color:white; border:none; border-radius:5px; padding:6px 12px; margin-left:4px; cursor:pointer;">Delete</button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    function updateEditFields(deptSelect, rowIdx) {
        var dept = deptSelect.value;
        // Year Level
        var yearLvlSelect = document.getElementById('edit_year_lvl_' + rowIdx);
        var strandCell = document.getElementById('edit_strand_cell_' + rowIdx);
        var courseCell = document.getElementById('edit_course_cell_' + rowIdx);
        if (yearLvlSelect) {
            yearLvlSelect.innerHTML = '';
            if (dept === 'JHS') {
                yearLvlSelect.innerHTML = `
                    <option value="Grade7">Grade 7</option>
                    <option value="Grade8">Grade 8</option>
                    <option value="Grade9">Grade 9</option>
                    <option value="Grade10">Grade 10</option>
                `;
            } else if (dept === 'SHS') {
                yearLvlSelect.innerHTML = `
                    <option value="Grade11">Grade 11</option>
                    <option value="Grade12">Grade 12</option>
                `;
            } else if (dept === 'College') {
                yearLvlSelect.innerHTML = `
                    <option value="1stYear">1st Year</option>
                    <option value="2ndYear">2nd Year</option>
                    <option value="3rdYear">3rd Year</option>
                    <option value="4thYear">4th Year</option>
                `;
            }
        }
        // Strand/Course
        var strandCourseCell = document.getElementById('edit_strand_course_' + rowIdx);
        if (strandCourseCell) {
            if (dept === 'SHS') {
                strandCourseCell.innerHTML = `<select name="edit_strand" id="edit_strand_${rowIdx}" style="width:100%;">
                    <option value="STEM">STEM</option>
                    <option value="HUMSS">HUMSS</option>
                    <option value="ABM">ABM</option>
                    <option value="TVL">TVL</option>
                </select>`;
            } else if (dept === 'College') {
                strandCourseCell.innerHTML = `<select name="edit_course" id="edit_course_${rowIdx}" style="width:100%;">
                    <option value="BTVTEd">BTVTEd</option>
                    <option value="BSCRIM">BSCRIM</option>
                    <option value="BSIS">BSIS</option>
                    <option value="BSEntrep">BSEntrep</option>
                    <option value="BSAIS">BSAIS</option>
                </select>`;
            } else {
                strandCourseCell.innerHTML = '<span>-</span>';
            }
        }
    }

    // Delete student functionality
    function deleteStudent(studentId, department, studentName) {
        showConfirmationModal(studentId, department, studentName);
    }

    // Function to show confirmation modal
    function showConfirmationModal(studentId, department, studentName) {
        const modal = document.getElementById('confirmationModal');
        const confirmYes = document.getElementById('confirmYes');
        const confirmNo = document.getElementById('confirmNo');
        const message = document.querySelector('.confirmation-message');

        // Update message with student name
        message.textContent = `Are you sure you want to delete ${studentName}?`;

        // Show modal
        modal.style.display = 'flex';

        // Handle Yes button click
        confirmYes.onclick = function() {
            modal.style.display = 'none';
            performDelete(studentId, department);
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

    // Function to perform the actual deletion
    function performDelete(studentId, department) {
        // Prepare data for AJAX request
        const data = {
            student_id: studentId,
            department: department
        };

        // Send AJAX request to delete student
        fetch('delete_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Show success notification and reload page
                showSuccessNotification('Student deleted successfully!');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                // Show error notification
                if (result.has_sanctions) {
                    showErrorNotification('Cannot delete student with active sanctions!');
                } else {
                    showErrorNotification(result.message || 'Failed to delete student');
                }
            }
        })
        .catch(error => {
            console.error('Error deleting student:', error);
            showErrorNotification('An error occurred while deleting the student');
        });
    }

    // Function to show success notification
    function showSuccessNotification(message) {
        const notification = document.getElementById('successNotification');
        if (notification) {
            notification.textContent = message;
            notification.style.display = 'block';

            // Auto-hide after 5 seconds
            setTimeout(function() {
                notification.style.display = 'none';
            }, 5000);
        }
    }

    // Function to show error notification
    function showErrorNotification(message) {
        const notification = document.getElementById('errorNotification');
        if (notification) {
            notification.textContent = message;
            notification.style.display = 'block';

            // Auto-hide after 5 seconds
            setTimeout(function() {
                notification.style.display = 'none';
            }, 5000);
        }
    }
    </script>
</body>
</html>