<?php
session_start();
$successMessage = '';
if (isset($_GET['violation_added']) && $_GET['violation_added'] == '1') {
    $successMessage = 'Violation added successfully!';
}

// Check if user is logged in and is guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    header("Location: ../index.php");
    exit();
}

// Initialize counters
$totalJHS = 0;
$totalSHS = 0;
$totalCollege = 0;
$totalStudents = 0;

// Get current school year
require_once '../connect.php';
$school_year_query = "SELECT id, school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year = $school_year_result->fetch_assoc();
$current_school_year_text = $current_school_year['school_year'] ?? '2025-2026';

// Get current semester name and ID
$current_semester = getCurrentSemesterName($conn);
$current_semester_id = getCurrentSemester($conn);

// Count students for current school year
$jhs_query = "SELECT COUNT(*) as count FROM jhs_students WHERE school_year = ?";
$shs_query = "SELECT COUNT(*) as count FROM shs_students WHERE school_year = ?";
$college_query = "SELECT COUNT(*) as count FROM college_students WHERE school_year = ?";

// Get JHS count
$stmt = $conn->prepare($jhs_query);
$stmt->bind_param("s", $current_school_year_text);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $totalJHS = (int)$row['count'];
}
$stmt->close();

// Get SHS count
$stmt = $conn->prepare($shs_query);
$stmt->bind_param("s", $current_school_year_text);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $totalSHS = (int)$row['count'];
}
$stmt->close();

// Get College count
$stmt = $conn->prepare($college_query);
$stmt->bind_param("s", $current_school_year_text);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $totalCollege = (int)$row['count'];
}
$stmt->close();

$totalStudents = $totalJHS + $totalSHS + $totalCollege;

// Count active violations for current school year and semester
$activeViolations = 0;
$current_year_id = $current_school_year['id'] ?? null;

if ($current_year_id) {
    $violations_query = "SELECT COUNT(*) as count FROM (
                        SELECT id FROM hs_violations WHERE school_year_id = ? AND semester_id = ?
                        UNION ALL
                        SELECT id FROM shs_violations WHERE school_year_id = ? AND semester_id = ?
                        UNION ALL
                        SELECT id FROM college_violations WHERE school_year_id = ? AND semester_id = ?
                        UNION ALL
                        SELECT id FROM college_crim_violations WHERE school_year_id = ? AND semester_id = ?
                        ) as all_violations";
    $stmt = $conn->prepare($violations_query);
    $stmt->bind_param("iiiiiiii", $current_year_id, $current_semester_id, $current_year_id, $current_semester_id, $current_year_id, $current_semester_id, $current_year_id, $current_semester_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $activeViolations = (int)$row['count'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/style.css">
    <title>Guard Dashboard - Nova Guard</title>
    <style>
         .logout-btn-wrapper {
            background: white;
            border-radius: 50%;
            padding: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logout-btn {
            transition: all 0.3s ease;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            display: block;
        }
        .logout-btn:hover {
            transform: scale(1.1);
        }
        .logout-btn svg {
            color: #e53e3e;
            display: block;
            transition: color 0.3s ease;
        }
        .logout-btn:hover svg {
            color: white;
        }
    </style>
</head>
<body>
    <?php if (!empty($successMessage)): ?>
        <div class="notification success"><?php echo $successMessage; ?></div>
    <?php endif; ?>
    <div class="dashboard-container">
        <div class="welcome-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                <div style="text-align: center;">
                    <h1 style="margin: 0;">NOVA GUARD</h1>
                    <h3 style="color: #272729; margin: 5px 0;">S.Y. <?php echo htmlspecialchars($current_school_year_text); ?> - <?php echo htmlspecialchars($current_semester); ?></h3>
                    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>
                </div>
            <div>
                <img src="../assets/icons/images/NovaGuardLogo.png" alt="NovaGuard Logo" style="width: 150px; height: auto; border-radius: 10px;">
            </div>
            </div>
        </div>

       
        <div class="menu-grid">
            <div class="menu-card">
                <h3>Student Management</h3>
                <p>Register new students and add new record</p>
                <a href="student_regselect.php" class="admin-button">Manage Students</a>
            </div>

            <div class="menu-card">
                <h3>Student Records</h3>
                <p>View list students with violation</p>
                <a href="student-records.php" class="admin-button">View Records</a>
            </div>
        </div>
        <!-- Logout Icon -->
        <div style="position: absolute; top: 80px; right: 20px;">
            <div class="logout-btn-wrapper">
                <button class="logout-btn" onclick="window.location.href='../logout.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16,17 21,12 16,7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Right-side Notification Panel (empty) -->
    <div id="notifPanel" style="position: fixed; top: 20px; right: 20px; width: 380px; height: calc(100vh - 40px); background: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: 0; z-index: 1100; display: none; border: 2px solid #231f1f; overflow: hidden;">
        <div style="display:flex; justify-content: space-between; align-items:center; padding: 16px; background: #ffffff; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; z-index: 10;">
            <h3 style="margin: 0;">Notifications</h3>
            <button id="notifClose" style="background:none; border:none; cursor:pointer; font-size:40px; line-height:1; transition: all 0.2s ease;">×</button>
        </div>
        <div style="padding: 16px; height: calc(100% - 132px); overflow-y: auto;">
            <div style="height: 100%; border: 2px dashed #e2e8f0; border-radius: 8px; display:flex; align-items:center; justify-content:center; color:#718096;">Empty panel</div>
        </div>
    </div>

    <script>
    (function(){
        var btn = document.getElementById('notifBtn');
        var panel = document.getElementById('notifPanel');
        var closeBtn = document.getElementById('notifClose');
        if (btn && panel) {
            btn.addEventListener('click', function(){
                var hidden = panel.style.display === 'none' || panel.style.display === '';
                panel.style.display = hidden ? 'block' : 'none';
                if (hidden) { 
                    loadNotifications(); 
                    // Mark all notifications as read when panel is opened
                    fetch('notifications_mark_read.php', { method: 'POST' })
                        .then(function(r){ return r.json(); })
                        .then(function(json){
                            if (json && json.ok) {
                                updateNotificationBadge(0);
                            }
                        })
                        .catch(function(){ /* noop */ });
                } else {
                    // Hide badge when panel is closed
                    updateNotificationBadge(0);
                }
            });
        }
        if (closeBtn && panel) {
            closeBtn.addEventListener('click', function(){ panel.style.display = 'none'; });
        }
        // Close panel when clicking outside of it (but not when clicking the bell button)
        document.addEventListener('click', function(e){
            if (!panel) return;
            var isVisible = panel.style.display === 'block';
            if (!isVisible) return;
            var clickedInsidePanel = panel.contains(e.target);
            var clickedBell = btn && btn.contains(e.target);
            if (!clickedInsidePanel && !clickedBell) {
                panel.style.display = 'none';
            }
        });

        function groupBlock(dateStr, items){
            var wrap = document.createElement('div');
            var h = document.createElement('h4'); h.textContent = dateStr; h.style.margin = '12px 0 8px';
            wrap.appendChild(h);
            var ul = document.createElement('ul'); ul.style.margin = '0 0 12px';
            items.forEach(function(it){
                var li = document.createElement('li');
                li.innerHTML = it.message;
                ul.appendChild(li);
            });
            wrap.appendChild(ul);
            return wrap;
        }

        function loadNotifications(){
            fetch('notifications_fetch.php')
                .then(function(r){ return r.json(); })
                .then(function(json){
                    if (!json || !json.ok) { return; }
                    var content = document.createElement('div');
                    var totalCount = 0;
                    Object.keys(json.data).sort(function(a,b){ return a < b ? 1 : -1; }).forEach(function(dateKey){
                        content.appendChild(groupBlock(dateKey, json.data[dateKey]));
                        totalCount += json.data[dateKey].length;
                    });
                    var blocks = panel.querySelectorAll('div');
                    var body = blocks[1];
                    if (body) { body.innerHTML = ''; body.appendChild(content); }
                    
                    // Update notification badge
                    updateNotificationBadge(totalCount);
                })
                .catch(function(){ /* noop */ });
        }
        
        function updateNotificationBadge(count) {
            var badge = document.getElementById('notificationBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.add('show');
            } else {
                badge.classList.remove('show');
            }
        }
        
        // Check for notifications on page load
        function checkNotificationsOnLoad() {
            fetch('notifications_fetch.php')
                .then(function(r){ return r.json(); })
                .then(function(json){
                    if (!json || !json.ok) { return; }
                    var unreadCount = 0;
                    Object.keys(json.data).forEach(function(dateKey){
                        json.data[dateKey].forEach(function(notification){
                            if (notification.guard_read === 'unread') {
                                unreadCount++;
                            }
                        });
                    });
                    updateNotificationBadge(unreadCount);
                })
                .catch(function(){ /* noop */ });
        }
        
        // Check notifications when page loads
        checkNotificationsOnLoad();
    })();
    </script>
</body>
</html> 