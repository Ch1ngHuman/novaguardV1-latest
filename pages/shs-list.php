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

$success = isset($_GET['success']) && $_GET['success'] === '1';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SHS Students Record</title>
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
        <h1 style= padding-top:40px; color: white; >SHS Students List</h1>
        <h3 style="text-align: center; color: white;">S.Y. <?php echo htmlspecialchars($current_school_year); ?> - <?php echo htmlspecialchars($current_semester); ?></h3>
    </div>

    <!-- Search Controls and Register Button -->
    <div style="margin: 20px 0; display: flex; justify-content: center; align-items: center; gap: 20px;">
      <input type="text" id="searchInput" placeholder="Search by name..." style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 220px;" autocomplete="off">
      <a href="shs_register.php" style="background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; font-weight: bold;">
        Register Student
      </a>
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
                        <th style="padding:8px;">Action</th>
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
                                <td style="padding:8px;">
                                    <a href="violation-list-shs.php?student_id=<?php echo $violation['student_id']; ?>&student_type=shs" style="background: #667eea; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; display: inline-block;">Add Violation</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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

    // Search functionality for SHS table only
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      const shsTable = document.getElementById('shsTable');

      function filterTable() {
        const search = searchInput.value.trim().toLowerCase();
        const tbody = shsTable.querySelector('tbody');
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