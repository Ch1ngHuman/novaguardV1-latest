<?php
// Lightweight notifications utility

/**
 * Ensure notifications table exists.
 */
function ensureNotificationsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(64) NOT NULL,
        message TEXT NOT NULL,
        admin_read ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
        guard_read ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @$conn->query($sql);
    
    // Add admin_read and guard_read columns if they don't exist (for existing tables)
    $checkAdminColumn = "SHOW COLUMNS FROM notifications LIKE 'admin_read'";
    $result = $conn->query($checkAdminColumn);
    if ($result && $result->num_rows == 0) {
        $alterSql = "ALTER TABLE notifications ADD COLUMN admin_read ENUM('unread', 'read') NOT NULL DEFAULT 'unread' AFTER message";
        @$conn->query($alterSql);
    }
    
    $checkGuardColumn = "SHOW COLUMNS FROM notifications LIKE 'guard_read'";
    $result = $conn->query($checkGuardColumn);
    if ($result && $result->num_rows == 0) {
        $alterSql = "ALTER TABLE notifications ADD COLUMN guard_read ENUM('unread', 'read') NOT NULL DEFAULT 'unread' AFTER admin_read";
        @$conn->query($alterSql);
    }
}

/**
 * Log a notification event.
 *
 * @param mysqli $conn
 * @param string $type e.g., 'student_registered','violation_added','sanction_completed','batch_imported'
 * @param string $message human-readable description
 */
function logNotification($conn, $type, $message) {
    if (!$conn) { return; }
    ensureNotificationsTable($conn);
    if ($stmt = $conn->prepare("INSERT INTO notifications (type, message) VALUES (?, ?)")) {
        $stmt->bind_param('ss', $type, $message);
        @$stmt->execute();
        $stmt->close();
    }
}



