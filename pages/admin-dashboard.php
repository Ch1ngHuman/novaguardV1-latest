<?php
session_start();
$successMessage = '';
if (isset($_GET['violation_added']) && $_GET['violation_added'] == '1') {
    $successMessage = 'Violation added successfully!';
}

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Add: Fetch total students and active violations
require_once '../connect.php';
$totalJHS = 0;
$totalSHS = 0;
$totalCollege = 0;
$totalStudents = 0;
$activeViolations = 0;

// Get current school year first
$school_year_query = "SELECT id, school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year = $school_year_result->fetch_assoc();
$current_year_id = $current_school_year['id'] ?? null;

// Get current semester name and ID
$current_semester = getCurrentSemesterName($conn);
$current_semester_id = getCurrentSemester($conn);

// Count students for current school year only
$current_school_year_text = $current_school_year['school_year'] ?? '2025-2026';

$jhs_query = "SELECT COUNT(*) as count FROM jhs_students WHERE school_year = ?";
$shs_query = "SELECT COUNT(*) as count FROM shs_students WHERE school_year = ?";
$college_query = "SELECT COUNT(*) as count FROM college_students WHERE school_year = ?";

// Prepare and execute JHS query
$stmt = $conn->prepare($jhs_query);
$stmt->bind_param("s", $current_school_year_text);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $totalJHS = (int)$row['count'];
}
$stmt->close();

// Prepare and execute SHS query
$stmt = $conn->prepare($shs_query);
$stmt->bind_param("s", $current_school_year_text);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $totalSHS = (int)$row['count'];
}
$stmt->close();

// Prepare and execute College query
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

// Count active violations for current school year and semester only
if ($current_year_id) {
    $violations_query = "SELECT COUNT(*) as count FROM (
                        SELECT id FROM hs_violations WHERE school_year_id = ?
                        UNION ALL
                        SELECT id FROM shs_violations WHERE school_year_id = ? AND semester_id = ?
                        UNION ALL
                        SELECT id FROM college_violations WHERE school_year_id = ? AND semester_id = ?
                        UNION ALL
                        SELECT id FROM college_crim_violations WHERE school_year_id = ? AND semester_id = ?
                        ) as all_violations";
    $stmt = $conn->prepare($violations_query);
    $stmt->bind_param("iiiiiii", $current_year_id, $current_year_id, $current_semester_id, $current_year_id, $current_semester_id, $current_year_id, $current_semester_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $activeViolations = (int)$row['count'];
    }
    $stmt->close();
}

// Add current school year to the page title
$current_school_year_text = $current_school_year['school_year'] ?? '2025-2026';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/style.css">
    <title>Admin Dashboard - Nova Guard</title>
    <style>
        .settings-btn-wrapper {
            background: white;
            border-radius: 50%;
            padding: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .settings-btn {
            transition: transform 0.2s ease;
        }
        .settings-btn:hover {
            transform: rotate(45deg);
        }
        .settings-btn svg {
            color: #667eea;
            display: block; /* This ensures proper centering */
        }
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
        .clear-notif-btn {
            width: 100%;
            padding: 10px;
            margin-top: 12px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s ease;
            /* Keep the button always visible at the bottom while scrolling */
            position: sticky;
            bottom: 0;
            z-index: 5;
            background-clip: padding-box;
            /* Create separation from the content when stuck */
            box-shadow: 0 -6px 12px rgba(0,0,0,0.06);
        }
        .clear-notif-btn:hover {
            background: #e53e3e;
        }
        #notifClose:hover{
            color: #e53e3e;
            transform: scale(1.2);
        }
        /* Darker, more readable notification text */
        #notifPanel,
        #notifPanel h3,
        #notifPanel h4,
        #notifPanel li,
        #notifPanel p,
        #notifPanel span {
            color: #1a202c; /* near-black for contrast */
        }
        #notifPanel li {
            line-height: 1.5;
            margin-bottom: 6px;
            padding: 6px 8px;
            border-radius: 6px;
            transition: background-color 0.15s ease, transform 0.1s ease;
        }
        #notifPanel li:hover {
            background-color: #edf2f7; /* light gray highlight */
            transform: translateY(-1px);
        }
        /* Red dot indicator for unread notifications */
        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #f44336;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: bold;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .notification-badge.show {
            display: flex;
        }
    </style>
</head>
<body>
    <?php if (!empty($successMessage)): ?>
        <div class="notification success"><?php echo $successMessage; ?></div>
    <?php endif; ?>
    <div class="dashboard-container">
        <!-- Notification Icon -->
        <div style="position: fixed; top: 20px; right: 20px;">
            <div class="settings-btn-wrapper" style="position: relative;">
                <button id="notifBtn" class="settings-btn" style="background: none; border: none; cursor: pointer; padding: 5px; display: block;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </button>
                <div id="notificationBadge" class="notification-badge"></div>
            </div>
        </div>
        <!-- Settings Icon -->
        <div style="position: fixed; top: 74px; right: 20px;">
            <div class="settings-btn-wrapper">
                <a href="settings.php" class="settings-btn" style="background: none; border: none; cursor: pointer; padding: 5px; display: block;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </a>
            </div>
        </div>
        <!-- Logout Icon -->
        <div style="position: fixed; top: 128px; right: 20px;">
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
        <div class="welcome-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                <div style="text-align: center;">
                    <h1 style="margin: 0;">Student Violation Monitoring System</h1>
                    <h3 style="color: #272729; margin: 5px 0;">S.Y. <?php echo htmlspecialchars($current_school_year_text); ?> - <?php echo htmlspecialchars($current_semester); ?></h3>
                    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>
                </div>
            </div>
        </div>

        <div class="stats-section">
            <a href="registered_students.php" style="text-decoration: none; color: inherit;">
            <div class="stat-card" style="cursor:pointer;">
                <div class="stat-number"><?php echo $totalStudents; ?></div>
                <div class="stat-label">Total Students</div>
                <div style="font-size: 12px; color: #555; margin-top: 4px;">
                    JHS: <?php echo $totalJHS; ?> | SHS: <?php echo $totalSHS; ?> | College: <?php echo $totalCollege; ?>
                </div>
            </div>
            </a>
            <div class="stat-card">
                <div class="stat-number"><?php echo $activeViolations; ?></div>
                <div class="stat-label">Active Violations</div>
            </div>
        </div>

        <div class="menu-grid">
            <div class="menu-card">
                <h3>Student Management</h3>
                <p>Register new student and manage student records</p>
                <a href="student_regselect.php" class="admin-button">Manage Students</a>
            </div>

            <div class="menu-card">
                <h3>Student Records</h3>
                <p>View list of students with violation</p>
                <a href="student-records.php" class="admin-button">View Records</a>
            </div>

            <div class="menu-card">
                <h3>Assign punishment</h3>
                <p>Assign punishment for student with violation</p>
                <a href="punishment.php" class="admin-button">Assign</a>
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
        <button id="clearNotifBtn" class="clear-notif-btn">Clear All Notifications</button>
    </div>

    <script>
    (function(){
        var btn = document.getElementById('notifBtn');
        var panel = document.getElementById('notifPanel');
        var closeBtn = document.getElementById('notifClose');
        var clearBtn = document.getElementById('clearNotifBtn');
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

        if (clearBtn && panel) {
            clearBtn.addEventListener('click', function(){
                showConfirmClear();
            });
        }

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
                    // replace inner content area (after header)
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
                            if (notification.admin_read === 'unread') {
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

        function showConfirmClear(){
            var blocks = panel.querySelectorAll('div');
            var body = blocks[1];
            if (!body) { return; }
            var wrap = document.createElement('div');
            wrap.style.padding = '16px';
            wrap.style.textAlign = 'center';
            wrap.style.border = '2px dashed #e2e8f0';
            wrap.style.borderRadius = '8px';
            var p = document.createElement('p');
            p.style.margin = '0 0 12px';
            p.textContent = 'Are you sure? This action cannot be undone.';
            var yes = document.createElement('button');
            yes.textContent = 'Yes';
            yes.style.marginRight = '8px';
            yes.style.background = '#f44336';
            yes.style.color = '#fff';
            yes.style.border = 'none';
            yes.style.padding = '8px 12px';
            yes.style.borderRadius = '6px';
            yes.style.cursor = 'pointer';
            var no = document.createElement('button');
            no.textContent = 'No';
            no.style.background = '#e2e8f0';
            no.style.border = 'none';
            no.style.padding = '8px 12px';
            no.style.borderRadius = '6px';
            no.style.cursor = 'pointer';
            wrap.appendChild(p); wrap.appendChild(yes); wrap.appendChild(no);
            body.innerHTML = ''; body.appendChild(wrap);

            yes.addEventListener('click', function(){
                fetch('notifications_clear.php', { method: 'POST' })
                    .then(function(r){ return r.json(); })
                    .then(function(json){
                        if (json && json.ok) {
                            // Show success state then reload notifications
                            p.textContent = 'All notifications cleared.';
                            setTimeout(loadNotifications, 600);
                        } else {
                            p.textContent = 'Failed to clear notifications.';
                        }
                    })
                    .catch(function(){ p.textContent = 'Failed to clear notifications.'; });
            });
            no.addEventListener('click', function(){ loadNotifications(); });
        }
    })();
    </script>
</body>
</html>
