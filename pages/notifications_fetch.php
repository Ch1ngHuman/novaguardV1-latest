<?php
header('Content-Type: application/json');
require_once '../connect.php';
require_once '../utils/notifications.php';

ensureNotificationsTable($conn);

// Fetch latest 100 notifications, newest first
$rows = [];
$res = $conn->query("SELECT id, type, message, admin_read, guard_read, created_at FROM notifications ORDER BY created_at DESC, id DESC LIMIT 100");
if ($res) {
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
}

// Group by date (Y-m-d)
$grouped = [];
foreach ($rows as $row) {
    $dateKey = substr($row['created_at'], 0, 10);
    if (!isset($grouped[$dateKey])) { $grouped[$dateKey] = []; }
    $grouped[$dateKey][] = $row;
}

echo json_encode([
    'ok' => true,
    'data' => $grouped,
]);
?>


