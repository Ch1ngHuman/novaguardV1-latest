<?php
session_start();
header('Content-Type: application/json');

// Only allow authenticated admins to clear notifications
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit();
}

require_once '../connect.php';

// Attempt to clear all notifications
$ok = false;
try {
    // Using DELETE for compatibility instead of TRUNCATE (which may require elevated privileges)
    $sql = "DELETE FROM notifications";
    if ($conn->query($sql) === TRUE) {
        $ok = true;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit();
}

echo json_encode(['ok' => $ok]);
exit();
?>


