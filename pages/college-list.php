<?php
require_once '../connect.php';
session_start();

// Fetch current school year
$school_year_query = "SELECT id, school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year_data = $school_year_result->fetch_assoc();
$current_school_year = $current_school_year_data['school_year'] ?? '2025-2026';
$current_school_year_id = $current_school_year_data['id'] ?? null;

// Get current semester name
$current_semester = getCurrentSemesterName($conn);
$current_semester_id = getCurrentSemester($conn);

// Fetch College students for current school year and semester
$college_query = "SELECT
    c.id as student_id,
    CONCAT(c.firstName, ' ', c.middleName, ' ', c.LastName) as full_name,
    GROUP_CONCAT(DISTINCT c.course SEPARATOR ', ') as course,
    c.year_lvl,
    GROUP_CONCAT(DISTINCT v.violation_type SEPARATOR ', ') as violations,
    COUNT(DISTINCT CASE WHEN v.violation_type IS NOT NULL THEN CONCAT(v.violation_type, v.date_recorded) END) as total_violations,
    GROUP_CONCAT(DISTINCT DATE_FORMAT(v.date_recorded, '%Y-%m-%d') SEPARATOR ', ') as date_reported
    FROM college_students c
    LEFT JOIN (
        SELECT student_id, violation_type, date_recorded, school_year_id, semester_id FROM college_violations
        UNION ALL
        SELECT student_id, violation_type, date_recorded, school_year_id, semester_id FROM college_crim_violations
    ) v ON c.id = v.student_id AND v.school_year_id = ? AND v.semester_id = ?
    WHERE c.school_year = ?
    GROUP BY c.id, c.firstName, c.middleName, c.lastName, c.year_lvl
    ORDER BY c.firstName ASC, c.middleName ASC, c.lastName ASC";

$college_stmt = $conn->prepare($college_query);
$college_stmt->bind_param('iis', $current_school_year_id, $current_semester_id, $current_school_year);
$college_stmt->execute();
$college_result = $college_stmt->get_result();
$college_violations = $college_result ? $college_result->fetch_all(MYSQLI_ASSOC) : [];
$college_stmt->close();

$success = isset($_GET['success']) && $_GET['success'] === '1';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>College Students Record</title>
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
    }
    .index-page {
        position: relative;
        padding-top: 20px;
    }

  </style>
</head>
<body>

  <div class="index-page">
    <?php if ($success): ?>
        <div class="success-notification" id="successNotification">
            Violation has been successfully recorded!
        </div>
    <?php endif; ?>

    <!-- Modify the back button div to include the new class -->
    <div class="icon-back-wrapper">
        <a href="student_regselect.php" class="icon-back-btn" aria-label="Back">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
    <div class="header-section" style="background: linear-gradient(135deg, #667eea 0%, #282bf0 100%); padding: 20px; border-radius: 10px; margin: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">    
        <h1 style= padding-top:40px; color: white; >College Students List</h1>
        <h3 style="text-align: center; color: white;">S.Y. <?php echo htmlspecialchars($current_school_year); ?> - <?php echo htmlspecialchars($current_semester); ?></h3>
    </div>

    <!-- Search Controls and Register Button -->
    <div style="margin: 20px 0; display: flex; justify-content: center; align-items: center; gap: 20px;">
      <input type="text" id="searchInput" placeholder="Search by name..." style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 220px;" autocomplete="off">
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <a href="college_register.php" style="background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; font-weight: bold;">
        Register Student
      </a>
      <?php endif; ?>
    </div>

    <!-- College Students Table -->
    <div style="margin: 20px;">
        <div class="wrapper">
            <p><strong>College Students</strong></p>
        </div>
        <div>
            <table id="collegeTable" style="width:100%; border-collapse:collapse; margin: 0 auto;">
                <thead>
                    <tr style="background:#667eea; color:white;">
                        <th style="padding:8px;">Name</th>
                        <th style="padding:8px;">Course</th>
                        <th style="padding:8px;">Year Level</th>
                        <th style="padding:8px;">Violation/s</th>
                        <th style="padding:8px;">Total Violation/s Committed</th>
                        <th style="padding:8px;">Date Reported</th>
                        <th style="padding:8px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($college_violations)): ?>
                    <tr style="background:white; border-bottom:1px solid #d0e7d0;">
                        <td colspan="7" style="text-align: center; padding:8px;">No College students registered</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($college_violations as $violation): ?>
                        <?php
                        // Check for major violations
                        $has_major_violation = false;
                        if (!empty($violation['violations'])) {
                            $violations_lower = strtolower($violation['violations']);
                            $has_major_violation = (strpos($violations_lower, 'vape') !== false || 
                                                   strpos($violations_lower, 'fire alarm') !== false ||
                                                   strpos($violations_lower, 'firealarm') !== false);
                        }
                        
                        // Determine background color
                        $bg_color = 'white';
                        if ($has_major_violation) {
                            $bg_color = '#FFCCCB';
                        } elseif ($violation['total_violations'] >= 3) {
                            $bg_color = '#FFCCCB';
                        } elseif ($violation['total_violations'] > 0) {
                            $bg_color = '#CCFEFF';
                        }
                        ?>
                        <tr style="background:<?php echo $bg_color; ?>; border-bottom:1px solid #d0e7d0;">
                            <td style="padding:8px;" class="name-cell"><?php echo htmlspecialchars($violation['full_name']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($violation['course']); ?></td>
                            <td style="padding:8px;" class="year-cell"><?php echo htmlspecialchars($violation['year_lvl']); ?></td>
                            <td style="padding:8px;"><?php echo !empty($violation['violations']) ? htmlspecialchars($violation['violations']) : '-'; ?></td>
                            <td style="padding:8px;" class="total-cell"><?php echo $violation['total_violations'] > 0 ? htmlspecialchars($violation['total_violations']) : '-'; ?></td>
                            <td style="padding:8px;"><?php echo !empty($violation['date_reported']) ? htmlspecialchars($violation['date_reported']) : '-'; ?></td>
                            <td style="padding:8px;">
                                <a href="javascript:void(0)"
                                    onclick="redirectToViolationPage(<?php echo $violation['student_id']; ?>, '<?php echo htmlspecialchars($violation['course']); ?>')"
                                    style="background: #667eea; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; display: inline-block; cursor: pointer;">
                                    Add Violation
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<script>
function redirectToViolationPage(studentId, course) {
    // Check if the course is BSCRIM
    if (course.toUpperCase() === 'BSCRIM') {
        // Redirect to BSCRIM violation page
        window.location.href = 'violation-list-collegeCrim.php?student_id=' + studentId + '&student_type=college';
    } else {
        // Redirect to regular college violation page
        window.location.href = 'violation-list-college.php?student_id=' + studentId + '&student_type=college';
    }
}
</script>

    <script>
    
    document.addEventListener('DOMContentLoaded', function() {
        var notif = document.getElementById('successNotification');
        if (notif) {
            setTimeout(function() {
                notif.style.display = 'none';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 4000); // match to your CSS animation (5 seconds)
        }
    });

    // Search functionality for College table only
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      const collegeTable = document.getElementById('collegeTable');

      function filterTable() {
        const search = searchInput.value.trim().toLowerCase();
        const tbody = collegeTable.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
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
      }
      searchInput.addEventListener('input', filterTable);
      filterTable(); // Initial call to set default view
    });
    </script>
  </div>
</body>
</html>