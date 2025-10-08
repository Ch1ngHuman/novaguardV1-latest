<?php
require_once '../connect.php';
session_start();

// Check user role and set appropriate dashboard link
$dashboardLink = '../index.php'; // default redirect
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $dashboardLink = 'admin-dashboard.php';
    } elseif ($_SESSION['role'] === 'guard') {
        $dashboardLink = 'guard-dashboard.php';
    }
}

// Fetch current school year
$school_year_query = "SELECT id, school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year_data = $school_year_result->fetch_assoc();
$current_school_year = $current_school_year_data['school_year'] ?? '2025-2026';
$current_school_year_id = $current_school_year_data['id'] ?? null;

// Get current semester name
$current_semester = getCurrentSemesterName($conn);
$current_semester_id = getCurrentSemester($conn);

// Fetch JHS students for current school year only (JHS doesn't use semester)
$jhs_query = "SELECT
    j.id as student_id,
    CONCAT(j.firstName, ' ', j.middleName, ' ', j.lastName) as full_name,
    j.year_lvl,
    GROUP_CONCAT(v.violation_type SEPARATOR ', ') as violations,
    COUNT(CASE WHEN v.violation_type IS NOT NULL THEN 1 END) as total_violations,
    GROUP_CONCAT(DATE_FORMAT(v.date_recorded, '%Y-%m-%d') SEPARATOR ', ') as date_reported
    FROM jhs_students j
    LEFT JOIN hs_violations v ON j.id = v.student_id AND v.student_type = 'jhs'
    WHERE j.school_year = ?
    GROUP BY j.id, j.firstName, j.middleName, j.lastName, j.year_lvl
    ORDER BY j.firstName ASC, j.middleName ASC, j.lastName ASC";

$jhs_stmt = $conn->prepare($jhs_query);
$jhs_stmt->bind_param('s', $current_school_year);
$jhs_stmt->execute();
$jhs_result = $jhs_stmt->get_result();
$jhs_violations = $jhs_result ? $jhs_result->fetch_all(MYSQLI_ASSOC) : [];
$jhs_stmt->close();

// Fetch SHS students for current school year and semester
$shs_query = "SELECT
    s.id as student_id,
    CONCAT(s.firstName, ' ', s.middleName, ' ', s.lastName) as full_name,
    s.strand,
    s.year_lvl,
    GROUP_CONCAT(v.violation_type SEPARATOR ', ') as violations,
    COUNT(CASE WHEN v.violation_type IS NOT NULL THEN 1 END) as total_violations,
    GROUP_CONCAT(DATE_FORMAT(v.date_recorded, '%Y-%m-%d') SEPARATOR ', ') as date_reported
    FROM shs_students s
    LEFT JOIN shs_violations v ON s.id = v.student_id AND v.school_year_id = ? AND v.semester_id = ?
    WHERE s.school_year = ?
    GROUP BY s.id, s.firstName, s.middleName, s.lastName, s.strand, s.year_lvl
    ORDER BY s.firstName ASC, s.middleName ASC, s.lastName ASC";

$shs_stmt = $conn->prepare($shs_query);
$shs_stmt->bind_param('iis', $current_school_year_id, $current_semester_id, $current_school_year);
$shs_stmt->execute();
$shs_result = $shs_stmt->get_result();
$shs_violations = $shs_result ? $shs_result->fetch_all(MYSQLI_ASSOC) : [];
$shs_stmt->close();

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
        SELECT cv.student_id, cv.violation_type, cv.date_recorded, cv.school_year_id, cv.semester_id FROM college_violations cv
        UNION ALL
        SELECT ccv.student_id, ccv.violation_type, ccv.date_recorded, ccv.school_year_id, ccv.semester_id FROM college_crim_violations ccv
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
  <title>Students Record</title>
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
        <a href="<?php echo $dashboardLink; ?>" class="icon-back-btn" aria-label="Back">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
    <div class="header-section" style="background: linear-gradient(135deg, #667eea 0%, #282bf0 100%); padding: 20px; border-radius: 10px; margin: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">    
        <h1 style= padding-top:40px; color: white; >List of Students With Violation</h1>
        <h3 style="text-align: center; color: white;">S.Y. <?php echo htmlspecialchars($current_school_year); ?> - <?php echo htmlspecialchars($current_semester); ?></h3>
    </div>

    <!-- Search, Filter Controls & Semester Selection -->
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
    <div style="margin: 20px;">
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
                        <th style="padding:8px;">Total Violation/s Committed</th>
                        <th style="padding:8px;">Date Reported</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jhs_violations)): ?>
                        <tr style="background:white; border-bottom:1px solid #d0e7d0;">
                            <td colspan="6" style="text-align: center; padding:8px;">No JHS students registered</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jhs_violations as $violation): ?>
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
                                <td style="padding:8px;" class="year-cell"><?php echo htmlspecialchars($violation['year_lvl']); ?></td>
                                <td style="padding:8px;"><?php echo !empty($violation['violations']) ? htmlspecialchars($violation['violations']) : '-'; ?></td>
                                <td style="padding:8px;" class="total-cell"><?php echo $violation['total_violations'] > 0 ? htmlspecialchars($violation['total_violations']) : '-'; ?></td>
                                <td style="padding:8px;"><?php echo !empty($violation['date_reported']) ? htmlspecialchars($violation['date_reported']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SHS Students Table -->
    <div style="margin: 20px;">
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
                        <th style="padding:8px;">Total Violation/s Committed</th>
                        <th style="padding:8px;">Date Reported</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shs_violations)): ?>
                        <tr style="background:white; border-bottom:1px solid #d0e7d0;">
                            <td colspan="7" style="text-align: center; padding:8px;">No SHS students registered</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shs_violations as $violation): ?>
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
                                <td style="padding:8px;"><?php echo htmlspecialchars($violation['strand']); ?></td>
                                <td style="padding:8px;" class="year-cell"><?php echo htmlspecialchars($violation['year_lvl']); ?></td>
                                <td style="padding:8px;"><?php echo !empty($violation['violations']) ? htmlspecialchars($violation['violations']) : '-'; ?></td>
                                <td style="padding:8px;" class="total-cell"><?php echo $violation['total_violations'] > 0 ? htmlspecialchars($violation['total_violations']) : '-'; ?></td>
                                <td style="padding:8px;"><?php echo !empty($violation['date_reported']) ? htmlspecialchars($violation['date_reported']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- College Students Table -->
    <div style="margin: 20px;">
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
                        <th style="padding:8px;">Total Violation/s Committed</th>
                        <th style="padding:8px;">Date Reported</th>
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
            table.parentElement.style.display = '';
          } else {
            table.parentElement.style.display = 'none';
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
    </script>
  </div>

</body>
</html>