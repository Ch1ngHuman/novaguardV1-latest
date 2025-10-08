<?php
session_start();
header('Content-Type: application/json');

// Only allow authenticated users to mark notifications as read
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit();
}

require_once '../connect.php';
require_once '../utils/notifications.php';

ensureNotificationsTable($conn);

// Mark notifications as read based on user role
$role = $_SESSION['role'];
if ($role === 'admin') {
    $sql = "UPDATE notifications SET admin_read = 'read' WHERE admin_read = 'unread'";
} elseif ($role === 'guard') {
    $sql = "UPDATE notifications SET guard_read = 'read' WHERE guard_read = 'unread'";
} else {
    echo json_encode(['ok' => false, 'error' => 'invalid_role']);
    exit();
}

if ($conn->query($sql)) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'database_error']);
}

$conn->close();
?>
